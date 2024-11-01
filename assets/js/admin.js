jQuery(document).ready(function ($) {
    // Assuming myButton is now a class name
    $(".shipi_label_button").on("click", function () {
        $('#shipi-loading').show();
        $("#shipi_load_order").hide();
        $("#shipi_label_popup").addClass("shipi-show");
        var order_id = $(this).data('order-id');
        $("#shipi_closePopup").val(order_id);
        
        $.ajax({
            url: ajax_params.ajax_url,
            type: 'POST',
            data: {
                action: 'get_order_details_shipi',
                security: ajax_params.security,
                order_id: order_id
            },
            success: function(response) {
                if(response.success){
                    var url = $("#get_base_shipi_url").val();
                    $('#shipi_load_order').attr('src', url + '&order=' + order_id);
                    console.log(url + '&order=' + order_id);
                }else{
                    alert(response.msg);
                }
            },
            error: function(xhr, status, error) {
                alert("Something Went Wrong...")
            }
        });
        return false;
    });

    $('#shipi_load_order').on('load', function() {
        // Hide the loader
        $('#shipi-loading').hide();
        $("#shipi_load_order").show();
    });
    $("#shipi_closePopup").on("click", function () {
        // Call the myshipi and get the data and show in the customer ui side. While close
        var order_id = $(this).val();
        $.ajax({
            url: ajax_params.ajax_url,
            type: 'POST',
            data: {
                action: 'get_tracking_shipi',
                security: ajax_params.security,
                order_id: order_id
            },
            success: function(response) {
                if(response.success){
                    $("." + order_id + "_shipi_label_button").html( response.data.tracking_no);
                    $("." + order_id + "_shipi_pdf_btn").attr('href', "https://app.myshipi.com/shipping_labels/_label_"+ response.data.tracking_no +".pdf").show();
                    $("." + order_id + "_shipi_brand_img").attr('src', "https://app.myshipi.com/assets/img/brand/"+ response.data.carrier +".jpg").show();
                }
            },
            error: function(xhr, status, error) {
            }
        });
        $("#shipi_label_popup").removeClass("shipi-show");
    });

    $(window).on("click", function (event) {
        if ($(event.target).is("#shipi_label_popup")) {
            $("#shipi_label_popup").removeClass("shipi-show");
        }
    });
});

// Listener in the parent window
function receiveMessage(event) {
    // Optionally check the event origin for security
    if (event.data && event.data.type === 'setHeight') {
        var iframe = document.getElementById('shipicontentFrame');
        iframe.style.height = event.data.height + 'px';
    }
}

window.addEventListener('message', receiveMessage, false);
