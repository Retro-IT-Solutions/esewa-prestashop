// Javascript for back office
$(document).ready(function() {
    // Cache selectors for better performance
    var $liveProductCode = $('#eSewa_live_product_code');
    var $liveMerchantSecret = $('#eSewa_live_merchant_secret');
    var $testProductCode = $('#eSewa_test_product_code');
    var $testMerchantSecret = $('#eSewa_test_merchant_secret');
    var $paymentMode = $('#eSewa_payment_mode');
    
    // Initially hide all input fields
    var selectedMode = $paymentMode.val();
    if (selectedMode == 1) { // Test Mode selected
        $liveProductCode.closest('.form-group').hide();
        $liveMerchantSecret.closest('.form-group').hide();
        $testProductCode.closest('.form-group').show();
        $testMerchantSecret.closest('.form-group').show();
    } else { // Live Mode selected
        $testProductCode.closest('.form-group').hide();
        $testMerchantSecret.closest('.form-group').hide();
        $liveProductCode.closest('.form-group').show();
        $liveMerchantSecret.closest('.form-group').show();
    }
    
    // Show input fields based on selected option
    $paymentMode.change(function() {
        selectedMode = $(this).val();
        if (selectedMode == 1) { // Test Mode selected
            $liveProductCode.closest('.form-group').hide();
            $liveMerchantSecret.closest('.form-group').hide();
            $testProductCode.closest('.form-group').show();
            $testMerchantSecret.closest('.form-group').show();
        } else { // Live Mode selected
            $testProductCode.closest('.form-group').hide();
            $testMerchantSecret.closest('.form-group').hide();
            $liveProductCode.closest('.form-group').show();
            $liveMerchantSecret.closest('.form-group').show();
        }
    });
});


