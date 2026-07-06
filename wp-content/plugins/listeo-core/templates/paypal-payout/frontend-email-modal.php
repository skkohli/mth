<?php

if (! defined('ABSPATH'))
    exit;

?>

<div class="wrapper-paypal-payout-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel"><?php _e('Email Required', 'listeo'); ?></h5>
        </div>
        <div class="modal-body">
            <?php _e('Please add your email address. This email address will use to send you the commission by using PayPal Payout service.', 'listeo'); ?>

            <form action="#" method="post">
                <label for="listeo_paypal_payout_email"><?php _e('PayPal Payout Email', 'listeo'); ?></label>
                <input type="email" name="listeo_paypal_payout_email" value="" class="listeo_paypal_payout_email" id="listeo_paypal_payout_email">
                <input type="submit" value="Save" class="listeo_paypal_payout_email_save_btn" id="listeo_paypal_payout_email_save_btn">
                <span class="listeo-loader-wrapper listeo_hidden">
                    <span class="fa fa-spinner fa-spin"></span>
                </span>

                <div class="listeo-errors-wrapper listeo-hidden">
                    <div class="listeo-error-message"></div>
                </div>

                <div class="listeo-success-wrapper listeo-hidden">
                    <div class="listeo-success-message"></div>
                </div>
            </form>
        </div>
    </div>
</div>
