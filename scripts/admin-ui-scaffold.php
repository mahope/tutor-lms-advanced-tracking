<?php
// Eksempelkode til en admin-side (ikke auto-loadet). Kopiér relevante bidder ind i pluginet når klart.
add_action('admin_menu', function () {
    add_management_page('Tracking Overview', 'Tracking Overview', 'manage_options', 'tlat-tracking-overview', function () {
        echo '<div class="wrap"><h1>Tracking Overview (scaffold)</h1><canvas id="tlatChart" width="400" height="200"></canvas>';
        echo '<p><a href="' . esc_url( wp_nonce_url(admin_url('admin-post.php?action=tlat_export_csv'), 'tlat_export_csv') ) . '">Download CSV (scaffold)</a></p></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script>
          const ctx = document.getElementById("tlatChart").getContext("2d");
          new Chart(ctx, { type: "bar", data: { labels: ["Mon","Tue","Wed"], datasets: [{ label: "Demo", data: [12,19,3] }] } });
        </script>';
    });
});

add_action('admin_post_tlat_export_csv', function () {
    if (! current_user_can('manage_options') || ! wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tlat_export_csv')) {
        wp_die('Not allowed');
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tlat-export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['user','course','progress']);
    fputcsv($out, ['demo','Course A','75%']);
    fclose($out);
    exit;
});
