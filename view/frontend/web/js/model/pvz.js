define(["ko"], function (ko) {
    "use strict";

    var pvzModel = {
        pvzCode: null,
        pvzData: null,
        list: [],
        init: function () {
        },

        /**
         * Set code of selected PVZ
         *
         * @param {String} pvz
         */
        setPvz: function (pvz) {
            this.pvzCode = pvz;
        },

        /**
         * Observable validation message
         *
         */
        errorValidationMessage: ko.observable(""),

        /**
         * Returns code of PVZ
         *
         * @returns {String}
         */
        getPvz: function () {
            return this.pvzCode;
        },

        setPvzData: function (pvzData) {
            this.pvzData = {
                name: pvzData.name,
                workTime: pvzData.workTime,
                fullAddress: pvzData.fullAddress,
                phone: pvzData.phone,
                latitude: pvzData.latitude,
                longitude: pvzData.longitude,
                cash: pvzData.cash,
                cashless: pvzData.cashless,
            };
        },

        setList: function (list) {
            this.list = list;
        },
    };

    return pvzModel;
});
