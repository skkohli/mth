<?php

/**
 * Dokan Store Open Close Time Widget
 *
 * @since 2.7.3
 *
 * @package dokan
 */
?>


<div class="opening-hours">
    <ul>
        <?php foreach ($dokan_store_time as $day => $value) : ?>
            <?php
            if (!isset($dokan_store_time[$day]) || (isset($dokan_store_time[$day]['status']) && 'close' === $dokan_store_time[$day]['status'])) {
                echo sprintf(
                    '<div class="open-close-day %1$s-time"><div class="working-day">%2$s</div> : <div class="store-times"> %3$s </div></div>',
                    esc_attr($day),
                    esc_html(ucfirst(dokan_get_translated_days($day))),
                    __('Off Day', 'dokan-lite')
                );
                continue;
            }

            // Get store opening time.
            $opening_times = !empty($dokan_store_time[$day]['opening_time']) ? $dokan_store_time[$day]['opening_time'] : [];

            // If dokan pro doesn't exists then show single item.
            if (!dokan()->is_pro_exists()) {
                // Get single time.
                $opening_times = !empty($opening_times) && is_array($opening_times) ? $opening_times[0] : [];
            }

            $times_length = !empty($opening_times) ? count((array) $opening_times) : 0;

            // Return if opening time length is zero.
            if (empty($times_length)) {
                continue;
            }
            ?>
            <li>
                <?php echo esc_html(ucfirst(dokan_get_translated_days($day))); ?>
                <?php
                // Get formatted store times.
                for ($index = 0; $index < $times_length; $index++) :
                    if (empty($dokan_store_time[$day]['opening_time'][$index])) {
                        continue;
                    }

                    $formatted_opening_time = dokan_format_date($dokan_store_time[$day]['opening_time'][$index], wc_time_format());
                    $formatted_closing_time = dokan_format_date($dokan_store_time[$day]['closing_time'][$index], wc_time_format());

                    echo sprintf(
                        '<span class="separator">%1$s  %2$s</span>',
                        esc_html($formatted_opening_time),
                        esc_html($formatted_closing_time)
                    );
                endfor;
                ?>

            </li>

        <?php endforeach; ?>
    </ul>
</div>