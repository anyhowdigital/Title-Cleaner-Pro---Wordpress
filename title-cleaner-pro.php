<?php
/* Plugin Name: Title Cleaner Pro
Description: Καθαρισμός τίτλων μέσω regex για custom post types, με cron, προεπισκόπηση και admin εργαλείο.
Version: 1.1
Author: Anyhow Media
*/
// === SETTINGS PAGE ===
add_action('admin_menu', function () {
    add_options_page(
        'Title Cleaner Pro', 'Title Cleaner Pro', 'manage_options', 'title-cleaner-pro', 'tcp_render_settings_page'
    );
});
add_action('admin_init', function () {
    register_setting('tcp_settings', 'tcp_post_type');
    register_setting('tcp_settings', 'tcp_regex');
    register_setting('tcp_settings', 'tcp_cron_interval');
});
function tcp_render_settings_page() {
    $post_types = get_post_types(['public' => true], 'objects');
    $current_type = get_option('tcp_post_type');
    $current_regex = get_option('tcp_regex');
    $current_cron = get_option('tcp_cron_interval');
    $log_path = WP_CONTENT_DIR . '/title-cleaner.log';
    ?>
    <div class="wrap">
        <h1>Title Cleaner Pro</h1>
        <form method="post" action="options.php">
            <?php settings_fields('tcp_settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Post Type</th>
                    <td>
                        <select name="tcp_post_type">
                            <?php foreach ($post_types as $type): ?>
                                <option value="<?php echo esc_attr($type->name); ?>" <?php selected($current_type, $type->name); ?>>
                                    <?php echo esc_html($type->labels->singular_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Regex Pattern</th>
                    <td>
                        <input type="text" name="tcp_regex" value="<?php echo esc_attr($current_regex); ?>" style="width:100%;" placeholder="\d+\s*-\s*(.*?),\s*[\d.]+\s*τ\.?μ\.?" />
                        <p class="description">Παράδειγμα: <code>\d+\s*-\s*(.*?),\s*[\d.]+\s*τ\.?μ\.?</code></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Συχνότητα Cron</th>
                    <td>
                        <select name="tcp_cron_interval">
                            <option value="15min" <?php selected($current_cron, '15min'); ?>>Κάθε 15 λεπτά</option>
                            <option value="30min" <?php selected($current_cron, '30min'); ?>>Κάθε 30 λεπτά</option>
                            <option value="hourly" <?php selected($current_cron, 'hourly'); ?>>Κάθε ώρα</option>
                            <option value="twicedaily" <?php selected($current_cron, 'twicedaily'); ?>>Κάθε 12 ώρες</option>
                            <option value="daily" <?php selected($current_cron, 'daily'); ?>>Μία φορά την ημέρα</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <form method="post">
            <?php submit_button('Καθαρισμός Τώρα', 'secondary', 'tcp_manual_clean'); ?>
        </form>
        <h2>🔍 Προεπισκόπηση Regex</h2>
        <pre style="background:#f9f9f9; padding:10px; max-height:300px; overflow:auto; border:1px solid #ccc;">
<?php
$current_type = get_option('tcp_post_type');
$current_regex = get_option('tcp_regex');
if ($current_type && $current_regex) {
    $sample_posts = get_posts([
        'post_type' => $current_type,
        'posts_per_page' => 5,
        'post_status' => 'publish'
    ]);
    if ($sample_posts) {
        foreach ($sample_posts as $post) {
            $title = $post->post_title;
            if (@preg_match("/$current_regex/u", $title, $matches)) {
                echo "Τίτλος: $title\n";
                echo "→ Καθαρισμένος: " . ($matches[1] ?? '⛔ Δεν βρέθηκε') . "\n\n";
            } else {
                echo "⛔ Λάθος regex ή δεν ταιριάζει: $title\n\n";
            }
        }
    } else {
        echo "Δεν βρέθηκαν δείγματα.";
    }
} else {
    echo "Ορίστε post type και regex για προεπισκόπηση.";
}
?>
        </pre>
        <h2>📜 Log</h2>
        <pre style="background:#f9f9f9; padding:10px; max-height:300px; overflow:auto; border:1px solid #ccc;">
<?php
if (file_exists($log_path)) {
    $log_lines = array_reverse(array_slice(file($log_path), -20));
    foreach ($log_lines as $line) {
        echo esc_html($line);
    }
} else {
    echo "Δεν υπάρχουν καταγραφές ακόμα.";
}
?>
        </pre>
    </div>
    <?php
}

// === CLEANER LOGIC ===
function tcp_clean_titles() {
    $type = get_option('tcp_post_type');
    $regex = get_option('tcp_regex');
    if (!$type || !$regex) return;

    $posts = get_posts([
        'post_type' => $type,
        'post_status' => 'publish',
        'posts_per_page' => -1
    ]);

    $count = 0;
    foreach ($posts as $post) {
        $title = $post->post_title;
        if (@preg_match("/$regex/u", $title, $matches)) {
            $cleaned = trim($matches[1] ?? '');
            if ($cleaned && $cleaned !== $title) {
                wp_update_post([
                    'ID' => $post->ID,
                    'post_title' => $cleaned
                ]);
                $count++;
            }
        }
    }

    $ts = date('Y-m-d H:i:s');
    $log = "[$ts] Cleaned $count titles\n";
    file_put_contents(WP_CONTENT_DIR . '/title-cleaner.log', $log, FILE_APPEND);
}

// === MANUAL CLEAN ACTION ===
add_action('admin_init', function () {
    if (isset($_POST['tcp_manual_clean']) && current_user_can('manage_options')) {
        tcp_clean_titles();
    }
});

// === CUSTOM CRON INTERVALS ===
add_filter('cron_schedules', function ($schedules) {
    $schedules['15min'] = ['interval' => 900, 'display' => 'Κάθε 15 λεπτά'];
    $schedules['30min'] = ['interval' => 1800, 'display' => 'Κάθε 30 λεπτά'];
    return $schedules;
});

// === CRON SETUP ===
function tcp_schedule_cron() {
    $interval = get_option('tcp_cron_interval', 'hourly');
    if (!wp_next_scheduled('tcp_cron_hook')) {
        wp_schedule_event(time(), $interval, 'tcp_cron_hook');
    }
}
register_activation_hook(__FILE__, 'tcp_schedule_cron');

// === CRON CLEANUP ===
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('tcp_cron_hook');
});

// === CRON ACTION ===
add_action('tcp_cron_hook', 'tcp_clean_titles');
