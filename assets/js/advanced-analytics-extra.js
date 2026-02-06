(function($){
  window.TutorAdvancedExtra = {
    fetchAndLog: function(){
      var root = (window.wpApiSettings && window.wpApiSettings.root) || (window.wp && window.wp.apiFetch && window.wp.apiFetch.use) || '/wp-json/';
      $.get(root + 'tutor-advanced/v1/engagement', function(d){ console.debug('Engagement', d); });
      $.get(root + 'tutor-advanced/v1/cohorts', function(d){ console.debug('Cohorts', d); });
    }
  };
  jQuery(function(){ setTimeout(function(){ try{ window.TutorAdvancedExtra.fetchAndLog(); }catch(e){} }, 1000); });
})(jQuery);
