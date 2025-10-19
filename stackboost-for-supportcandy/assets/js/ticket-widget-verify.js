jQuery(document).ready(function($) {
    if ($('.wpsc-itw-container .wpsc-itw-title:contains("Company Directory")').length) {
        console.log('StackBoost Ticket Widget: The Company Directory widget has been successfully rendered in the DOM.');
    } else {
        console.log('StackBoost Ticket Widget: The Company Directory widget was not found in the DOM.');
    }
});
