define(['jquery'], function ($) {
    "use strict";
    function getPickupPoint(config) {
        let postcode = $("#carriers_cdek_postcode").val();
        let countryId = $("#carriers_cdek_country_id").val();
        let selectPickupPoint = $("#carriers_cdek_pickup_point");

        selectPickupPoint.empty();
        selectPickupPoint.append(new Option('Select Pickup Point', ''));
        $.ajax({
            url: config.ajaxUrlValue,
            type: "POST",
            data: {
                postcode: postcode,
                countryId: countryId
            },
            showLoader: true,
            cache: false,
            success: function(response){
                if (response.status !== 'error') {
                    $.map(response.pickup_points, function (index) {
                        let optionText = index.name + ' (' + index.location.address_full + ')';
                        selectPickupPoint.append(new Option(optionText, index.code));
                    });
                }
            }
        });
    }

    return function(config) {
        $("#carriers_cdek_postcode").on('change', function() {
            getPickupPoint(config)
        });

        $("#carriers_cdek_country_id").on('change', function() {
            getPickupPoint(config)
        });
    }
});
