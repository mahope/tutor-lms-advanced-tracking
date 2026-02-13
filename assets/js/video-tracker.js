/**
 * TLAT Video Tracker
 *
 * Tracks video play/pause/ended/timeupdate events for:
 * - HTML5 <video> elements
 * - YouTube embeds (iframe API)
 * - Vimeo embeds (Player API)
 *
 * Sends progress data to the server at configurable intervals.
 * Maintains a segments array tracking which portions of the video have been watched.
 */
(function($) {
    'use strict';

    if (typeof tlatVideoTracker === 'undefined') {
        return;
    }

    var config = tlatVideoTracker;
    var INTERVAL = (parseInt(config.interval, 10) || 5) * 1000; // ms

    /**
     * Tracker instance for a single video.
     */
    function VideoTracker(opts) {
        this.videoUrl      = opts.videoUrl || '';
        this.provider      = opts.provider || 'html5';
        this.lessonId      = parseInt(config.lessonId, 10) || 0;
        this.courseId       = parseInt(config.courseId, 10) || 0;
        this.duration       = 0;
        this.currentTime    = 0;
        this.segments       = []; // [[start, end], ...]
        this.segmentStart   = null;
        this.playing        = false;
        this.timer          = null;
        this.lastSendTime   = 0;
    }

    VideoTracker.prototype.onPlay = function() {
        this.playing = true;
        this.segmentStart = Math.floor(this.currentTime);
        this.sendProgress('play');
        this.startTimer();
    };

    VideoTracker.prototype.onPause = function() {
        this.closeSegment();
        this.playing = false;
        this.sendProgress('pause');
        this.stopTimer();
    };

    VideoTracker.prototype.onEnded = function() {
        this.closeSegment();
        this.playing = false;
        this.sendProgress('ended');
        this.stopTimer();
    };

    VideoTracker.prototype.onTimeUpdate = function(currentTime, duration) {
        this.currentTime = Math.floor(currentTime);
        if (duration && duration > 0) {
            this.duration = Math.floor(duration);
        }
    };

    VideoTracker.prototype.closeSegment = function() {
        if (this.segmentStart !== null) {
            var end = Math.floor(this.currentTime);
            if (end > this.segmentStart) {
                this.segments.push([this.segmentStart, end]);
            }
            this.segmentStart = Math.floor(this.currentTime);
        }
    };

    VideoTracker.prototype.startTimer = function() {
        var self = this;
        if (this.timer) return;
        this.timer = setInterval(function() {
            if (self.playing) {
                self.closeSegment();
                self.sendProgress('timeupdate');
            }
        }, INTERVAL);
    };

    VideoTracker.prototype.stopTimer = function() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    };

    VideoTracker.prototype.sendProgress = function(event) {
        // Throttle: don't send more than once per 3 seconds for timeupdate
        var now = Date.now();
        if (event === 'timeupdate' && (now - this.lastSendTime) < 3000) {
            return;
        }
        this.lastSendTime = now;

        if (!this.videoUrl || !this.duration) {
            return;
        }

        var data = {
            video_url: this.videoUrl,
            video_provider: this.provider,
            lesson_id: this.lessonId,
            course_id: this.courseId,
            duration: this.duration,
            current_time: this.currentTime,
            segments: JSON.stringify(this.segments),
            event: event
        };

        // Clear sent segments (keep current open segment tracked via segmentStart)
        this.segments = [];

        // Use REST API if available, fallback to AJAX
        if (config.restUrl && config.restNonce) {
            $.ajax({
                url: config.restUrl,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', config.restNonce);
                },
                data: data
            });
        } else {
            data.action = 'tlat_track_video_progress';
            data.nonce = config.nonce;
            $.post(config.ajaxUrl, data);
        }
    };

    // =========================================================================
    // HTML5 Video Tracker
    // =========================================================================

    function initHTML5Videos() {
        var videos = document.querySelectorAll('video');
        videos.forEach(function(video) {
            var src = video.currentSrc || video.src || '';
            if (!src) {
                // Try <source> elements
                var source = video.querySelector('source');
                if (source) src = source.src;
            }
            if (!src) return;

            var tracker = new VideoTracker({
                videoUrl: src,
                provider: 'html5'
            });

            video.addEventListener('loadedmetadata', function() {
                tracker.duration = Math.floor(video.duration);
            });
            video.addEventListener('play', function() {
                tracker.duration = Math.floor(video.duration);
                tracker.onPlay();
            });
            video.addEventListener('pause', function() {
                tracker.onPause();
            });
            video.addEventListener('ended', function() {
                tracker.onEnded();
            });
            video.addEventListener('timeupdate', function() {
                tracker.onTimeUpdate(video.currentTime, video.duration);
            });
        });
    }

    // =========================================================================
    // YouTube Tracker (iframe API)
    // =========================================================================

    var ytTrackers = {};
    var ytReady = false;

    function initYouTubeVideos() {
        var iframes = document.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="youtu.be"]');
        if (iframes.length === 0) return;

        // Load the YouTube iframe API if not yet loaded
        if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
            // Tag already registered by wp_register_script, enqueue it
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        } else {
            onYTReady();
        }

        // YouTube API calls this when ready
        window.onYouTubeIframeAPIReady = function() {
            onYTReady();
        };

        function onYTReady() {
            if (ytReady) return;
            ytReady = true;

            iframes.forEach(function(iframe, index) {
                // Ensure iframe has enablejsapi=1
                var src = iframe.src;
                if (src.indexOf('enablejsapi') === -1) {
                    iframe.src = src + (src.indexOf('?') > -1 ? '&' : '?') + 'enablejsapi=1';
                }

                // Give iframe an ID if it doesn't have one
                if (!iframe.id) {
                    iframe.id = 'tlat-yt-player-' + index;
                }

                try {
                    var player = new YT.Player(iframe.id, {
                        events: {
                            onReady: function(e) {
                                var videoUrl = e.target.getVideoUrl ? e.target.getVideoUrl() : src;
                                var tracker = new VideoTracker({
                                    videoUrl: videoUrl,
                                    provider: 'youtube'
                                });
                                ytTrackers[iframe.id] = { player: e.target, tracker: tracker };

                                // Poll for time updates since YouTube doesn't have ontimeupdate
                                setInterval(function() {
                                    if (tracker.playing && e.target.getCurrentTime) {
                                        tracker.onTimeUpdate(
                                            e.target.getCurrentTime(),
                                            e.target.getDuration()
                                        );
                                    }
                                }, 1000);
                            },
                            onStateChange: function(e) {
                                var entry = ytTrackers[iframe.id];
                                if (!entry) return;
                                var tracker = entry.tracker;
                                var p = entry.player;

                                // Update duration
                                if (p.getDuration) {
                                    tracker.duration = Math.floor(p.getDuration());
                                }

                                switch (e.data) {
                                    case YT.PlayerState.PLAYING:
                                        if (p.getCurrentTime) {
                                            tracker.currentTime = Math.floor(p.getCurrentTime());
                                        }
                                        tracker.onPlay();
                                        break;
                                    case YT.PlayerState.PAUSED:
                                        if (p.getCurrentTime) {
                                            tracker.currentTime = Math.floor(p.getCurrentTime());
                                        }
                                        tracker.onPause();
                                        break;
                                    case YT.PlayerState.ENDED:
                                        tracker.currentTime = tracker.duration;
                                        tracker.onEnded();
                                        break;
                                }
                            }
                        }
                    });
                } catch (err) {
                    // YouTube iframe API can fail if the iframe is cross-origin restricted
                }
            });
        }
    }

    // =========================================================================
    // Vimeo Tracker
    // =========================================================================

    function initVimeoVideos() {
        var iframes = document.querySelectorAll('iframe[src*="vimeo.com"]');
        if (iframes.length === 0) return;

        // Load Vimeo Player SDK if not yet loaded
        function loadVimeoSDK(callback) {
            if (typeof Vimeo !== 'undefined' && typeof Vimeo.Player !== 'undefined') {
                callback();
                return;
            }
            var tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            tag.onload = callback;
            document.head.appendChild(tag);
        }

        loadVimeoSDK(function() {
            iframes.forEach(function(iframe) {
                var src = iframe.src;
                try {
                    var player = new Vimeo.Player(iframe);
                    var tracker = new VideoTracker({
                        videoUrl: src,
                        provider: 'vimeo'
                    });

                    player.getDuration().then(function(dur) {
                        tracker.duration = Math.floor(dur);
                    });

                    player.on('play', function() {
                        player.getDuration().then(function(dur) {
                            tracker.duration = Math.floor(dur);
                        });
                        tracker.onPlay();
                    });

                    player.on('pause', function() {
                        tracker.onPause();
                    });

                    player.on('ended', function() {
                        tracker.onEnded();
                    });

                    player.on('timeupdate', function(data) {
                        tracker.onTimeUpdate(data.seconds, data.duration);
                    });
                } catch (err) {
                    // Vimeo Player can fail if iframe is restricted
                }
            });
        });
    }

    // =========================================================================
    // Initialize all trackers when DOM is ready
    // =========================================================================

    $(document).ready(function() {
        // Small delay to let Tutor LMS render video elements
        setTimeout(function() {
            initHTML5Videos();
            initYouTubeVideos();
            initVimeoVideos();
        }, 1000);
    });

})(jQuery);
