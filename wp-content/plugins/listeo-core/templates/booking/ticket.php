<?php

$event_details = $data->event_details;
$ticket_code = $data->ticket_code;
$qr_base64 = $data->qr_base64;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Booking Confirmation', 'listeo_core'); ?> - <?php echo esc_html($event_details['title']); ?></title>

</head>

<body>
    <div class="text-container">

        <div class="left-side">
            <?php $listing_logo = get_post_meta($event_details['listing_id'], '_listing_logo', true);
            if ($listing_logo) : ?>
                <img src="<?php echo esc_url($listing_logo); ?>" alt="<?php echo esc_html($event_details['title']); ?>" style="max-width: 150px; height: auto">
            <?php endif; ?>
        </div>

        <div class="right-side">
            <strong><?php esc_html_e('Ticket ID', 'listeo_core'); ?>:</strong> <?php echo esc_html($ticket_code); ?><br>
            <?php if (!empty($event_details['date'])) : ?>
                <strong><?php esc_html_e('Event Date', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['date']); ?>
            <?php endif; ?>
        </div>
    </div>
    </div>
    <!-- Invoice -->
    <div id="invoice">

        <!-- Client & Supplier -->
        <div class="col-md-9 ticket-content">


            <h2><?php echo esc_html($event_details['title']); ?></h2>
            <?php if (!empty($event_details['date'])) : ?>
                <p style="font-size: 18px; font-weight: 500; color: #333; margin-top: 10px; margin-bottom: 20px;">
                    <strong><?php esc_html_e('Event Date', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['date']); ?>
                </p>
            <?php endif; ?>
            <p id="details">
                <strong><?php esc_html_e('Ticket ID', 'listeo_core'); ?>:</strong> <?php echo esc_html($ticket_code); ?><br>
                <?php if (get_option('listeo_ticket_show_booking_id')) : ?>
                    <strong><?php esc_html_e('Booking ID', 'listeo_core'); ?>:</strong> <?php echo esc_html($data->booking_id); ?> <br>
                <?php endif; ?>

                <strong><?php esc_html_e('Attendee', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['name']); ?> <br>

                <strong><?php esc_html_e('Price', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['price']); ?> <br>
                <?php if (isset($event_details['tickets']) && !empty($event_details['tickets'])) : ?>
                    <strong><?php esc_html_e('Tickets', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['tickets']); ?> <br>
                <?php endif; ?>
                <?php if (isset($event_details['childers']) && !empty($event_details['children'])) : ?>
                    <strong><?php esc_html_e('children', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['children']); ?> <br>
                <?php endif; ?>
                <?php if (isset($event_details['guests']) && !empty($event_details['guests'])) : ?>
                    <strong><?php esc_html_e('Guests', 'listeo_core'); ?>:</strong> <?php echo esc_html($event_details['guests']); ?> <br>
                <?php endif; ?>


                <?php if (isset($event_details['extra_services']) && !empty($event_details['extra_services'])) { ?>
                    <strong><?php esc_html_e('Extras', 'listeo_core'); ?>:</strong>
                    <?php echo  listeo_get_extra_services_html($event_details['extra_services']); ?>

                <?php } ?>
            </p>
        </div>


        <!-- Invoice -->
        <div class="col-md-3 ticket-qr">
            <div class="qrcode">
                <img src="data:image/png;base64,<?php echo $qr_base64; ?>" alt="<?php esc_html_e('QR Code', 'listeo_core'); ?>">
            </div>
        </div>

        <div class="ticket-badge">
            <?php esc_html_e('E-TICKET', 'listeo_core'); ?>
        </div>

    </div>
    <?php
    $terms = get_option('listeo_ticket_terms');
    if ($terms) : ?>
        <div class="text-container no-flex">
            <strong style="font-size: 16px; margin-bottom: 10px;"><?php esc_html_e('Terms & Conditions', 'listeo_core'); ?></strong>
            <p style="font-size: 14px; line-height: 24px; margin-bottom: 50px;"><?php echo $terms; ?></p>
        </div>
    <?php endif; ?>
    <!-- Print Button -->
    <a href="javascript:window.print()" class="print-button"><?php esc_html_e('Print this ticket', 'listeo_core'); ?></a>

    <style type="text/css">
        @charset "UTF-8";

        /*
* Author: Vasterad
* URL: http://purethemes.net
*/

        /* ------------------------------------------------------------------- */
        /* Invoice Styles
---------------------------------------------------------------------- */
        @import url('https://fonts.googleapis.com/css?family=Poppins:400,500,600');

        html {
            font-family: "Poppins", "HelveticaNeue", "Helvetica Neue", Helvetica, Arial, sans-serif;
            text-transform: none;
            font-size: 100%;
        }

        strong {
            font-weight: 600;
            color: #333;
            display: inline-block;
        }

        body {
            background: #fff;
            color: #666;
            font-weight: 300;
            line-height: 28px;
            overflow-wrap: break-word;
        }

        #invoice {
            background: white;
            width: auto;
            max-width: 1000px;
            width: 100%;
            /*    padding: 40px;
    padding-bottom: 70px;*/
            margin: 20px auto 20px auto;
            border-radius: 0;
            border: 1px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }


        .text-container {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            margin-top: 60px;
        }

        .left-side {
            width: 50%;
        }

        .right-side {
            width: 50%;
            text-align: right;
        }

        .ticket-logo {
            max-height: 100px;
            max-width: 300px;
        }

        .text-container.no-flex {
            display: block;
            margin-top: 40px;
        }

        .ticket-badge {
            position: absolute;
            bottom: 0;
            left: 0;
            background: #000;
            color: #fff;
            font-weight: 600;
            font-size: 22px;
            padding: 10px 20px;
        }

        .ticket-content.col-md-9 {
            padding: 40px;
            padding-bottom: 80px;
            width: 70%;
        }

        .ticket-content ul {
            padding: 0;
            margin: 0;
            display: inline-block;
        }

        .ticket-content ul li {
            display: inline;
            border-right: 1px solid #b7b7b7;
            padding: 0 10px 0 10px;
            margin: 0;
            line-height: 20px;
        }

        .ticket-content ul li:first-child {
            padding-left: 0;
        }

        .ticket-content ul li:last-child {
            border: none;
        }

        .ticket-qr.col-md-3 {
            border: 1px solid #000;
            margin: 40px;
            width: 30%;
        }

        @media screen and (max-width: 768px) {
            #invoice {
                flex-direction: column;
                flex-wrap: wrap;
                padding: 30px;
                box-sizing: border-box;
            }

            .ticket-content.col-md-9 {
                width: 100%;
                padding: 0px;
            }

            .col-md-3.ticket-qr {
                margin: 30px 0 40px 0;
                width: 100%;
            }
        }

        h1,
        h2,
        h3 {
            font-weight: 300;
            color: #333;
            clear: both;
            margin: 0;
        }

        h2 {
            font-size: 26px;
            line-height: 1;
            font-weight: 600;
            margin: 0 0 5px 0;
        }

        p {
            margin: 0;
            padding-bottom: 0px;
            clear: both;
        }

        #logo img {
            max-height: 44px;
        }

        #details {
            text-align: left;
            margin-top: 30px;
        }

        #footer {
            width: 100%;
            border-top: 1px solid #ddd;
            margin: 60px 0 0 0;
            padding: 20px 0 0 0;
            list-style: none;
            font-size: 15px;
        }

        #footer li {
            display: inline-block;
            padding: 0 20px;
            border-right: 1px solid #ddd;
            line-height: 11px;
        }

        #footer li:first-child {
            padding-left: 0;
        }

        #footer li:last-child {
            border: none;
        }

        #footer li span {
            color: #f91942;
        }

        .margin-top-20 {
            margin-top: 20px;
        }

        .margin-bottom-5 {
            margin-bottom: 5px;
        }

        .print-button,
        .print-button:hover {
            line-height: 24px;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            background-color: #222;
            border-radius: 50px;
            padding: 10px 20px;
            display: block;
            text-align: center;
            margin: 0px auto 0px auto;
            max-width: 190px;
            transition: 0.3s;
            text-decoration: none !important;
            outline: none !important;
        }

        .print-button:hover {
            background-color: #222;
        }


        .qrcode {
            border-radius: 0;
            overflow: hidden;
            width: 100%
        }

        .qrcode img {
            object-fit: fill;
            width: 100%
        }

        .col-md-1,
        .col-md-2,
        .col-md-3,
        .col-md-4,
        .col-md-5,
        .col-md-6,
        .col-md-7,
        .col-md-8,
        .col-md-9,
        .col-md-10,
        .col-md-11,
        .col-md-12 {
            float: left;
        }

        .col-md-12 {
            width: 100%
        }

        .col-md-11 {
            width: 91.66666667%
        }

        .col-md-10 {
            width: 83.33333333%
        }

        .col-md-9 {
            width: 75%
        }

        .col-md-8 {
            width: 66.66666667%
        }

        .col-md-7 {
            width: 58.33333333%
        }

        .col-md-6 {
            width: 50%
        }

        .col-md-5 {
            width: 41.66666667%
        }

        .col-md-4 {
            width: 33.33333333%
        }

        .col-md-3 {
            width: 25%
        }

        .col-md-2 {
            width: 16.66666667%
        }

        .col-md-1 {
            width: 8.33333333%
        }

        /* Print Styles*/
        @media print {

            .print-button {
                display: none;
                opacity: 0;
                visibility: hidden;
                height: 0;
            }

            .ticket-badge {
                bakground: transparent;
                border: 1px solid #000;
                color: #000;
                bottom: -1px;
                left: -1px;
            }

            body {
                background: #fff;
                height: 100%;
                color: #666;
            }

            strong,
            th,
            h1,
            h2,
            h3 {
                color: #111;
            }

            table,
            th,
            #footer,
            #footer li {
                border-color: #bbb;
            }


            @page {
                size: A4;
                margin: 0 17mm;
            }

            body {
                margin: 2mm;
            }

            #invoice {
                max-width: 100%;
                width: 100%;
                padding: 0;
                margin: 0;
                margin-top: 5mm;
                position: relative;
            }

            #footer {
                position: fixed;
                bottom: 17mm;
            }

            .content-block,
            p {
                page-break-inside: avoid;
            }

            html,
            body {
                width: 210mm;
                height: 297mm;
            }

            .col-md-1,
            .col-md-2,
            .col-md-3,
            .col-md-4,
            .col-md-5,
            .col-md-6,
            .col-md-7,
            .col-md-8,
            .col-md-9,
            .col-md-10,
            .col-md-11,
            .col-md-12 {
                float: left;
            }

            .col-md-12 {
                width: 100%
            }

            .col-md-11 {
                width: 91.66666667%
            }

            .col-md-10 {
                width: 83.33333333%
            }

            .col-md-9 {
                width: 75%
            }

            .col-md-8 {
                width: 66.66666667%
            }

            .col-md-7 {
                width: 58.33333333%
            }

            .col-md-6 {
                width: 50%
            }

            .col-md-5 {
                width: 41.66666667%
            }

            .col-md-4 {
                width: 33.33333333%
            }

            .col-md-3 {
                width: 25%
            }

            .col-md-2 {
                width: 16.66666667%
            }

            .col-md-1 {
                width: 8.33333333%
            }

            .col-md-pull-12 {
                right: 100%
            }

            .col-md-pull-11 {
                right: 91.66666667%
            }

            .col-md-pull-10 {
                right: 83.33333333%
            }

            .col-md-pull-9 {
                right: 75%
            }

            .col-md-pull-8 {
                right: 66.66666667%
            }

            .col-md-pull-7 {
                right: 58.33333333%
            }

            .col-md-pull-6 {
                right: 50%
            }

            .col-md-pull-5 {
                right: 41.66666667%
            }

            .col-md-pull-4 {
                right: 33.33333333%
            }

            .col-md-pull-3 {
                right: 25%
            }

            .col-md-pull-2 {
                right: 16.66666667%
            }

            .col-md-pull-1 {
                right: 8.33333333%
            }

            .col-md-pull-0 {
                right: auto
            }

            .col-md-push-12 {
                left: 100%
            }

            .col-md-push-11 {
                left: 91.66666667%
            }

            .col-md-push-10 {
                left: 83.33333333%
            }

            .col-md-push-9 {
                left: 75%
            }

            .col-md-push-8 {
                left: 66.66666667%
            }

            .col-md-push-7 {
                left: 58.33333333%
            }

            .col-md-push-6 {
                left: 50%
            }

            .col-md-push-5 {
                left: 41.66666667%
            }

            .col-md-push-4 {
                left: 33.33333333%
            }

            .col-md-push-3 {
                left: 25%
            }

            .col-md-push-2 {
                left: 16.66666667%
            }

            .col-md-push-1 {
                left: 8.33333333%
            }

            .col-md-push-0 {
                left: auto
            }

            .col-md-offset-12 {
                margin-left: 100%
            }

            .col-md-offset-11 {
                margin-left: 91.66666667%
            }

            .col-md-offset-10 {
                margin-left: 83.33333333%
            }

            .col-md-offset-9 {
                margin-left: 75%
            }

            .col-md-offset-8 {
                margin-left: 66.66666667%
            }

            .col-md-offset-7 {
                margin-left: 58.33333333%
            }

            .col-md-offset-6 {
                margin-left: 50%
            }

            .col-md-offset-5 {
                margin-left: 41.66666667%
            }

            .col-md-offset-4 {
                margin-left: 33.33333333%
            }

            .col-md-offset-3 {
                margin-left: 25%
            }

            .col-md-offset-2 {
                margin-left: 16.66666667%
            }

            .col-md-offset-1 {
                margin-left: 8.33333333%
            }

            .col-md-offset-0 {
                margin-left: 0
            }

        }
    </style>
</body>

</html>