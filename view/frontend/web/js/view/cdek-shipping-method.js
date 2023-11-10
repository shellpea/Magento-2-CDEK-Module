define([
    "jquery",
    "uiComponent",
    "ko",
    "Magento_Checkout/js/model/quote",
    "mage/url",
    "mage/storage",
    "mage/translate",
    "Magento_Checkout/js/model/resource-url-manager",
    "Magento_Checkout/js/model/full-screen-loader",
    "Magento_Checkout/js/model/error-processor",
    "Magento_Ui/js/model/messageList",
    "Shellpea_CDEK/js/model/pvz",
], function (
    $,
    Component,
    ko,
    quote,
    url,
    storage,
    $t,
    resourceUrlManager,
    fullScreenLoader,
    errorProcessor,
    globalMessageList,
    pvzModel
) {
    "use strict";

    const emptyPvz = {
        code: null,
        name: null,
        workTime: null,
        fullAddress: null,
        latitude: null,
        longitude: null,
        cash: null,
        cashless: null,
        phone: null,
        location: null,
        type: null,
        buttonText: "Получить здесь",
        isSelected: ko.observable(false),
        nearestMetroStation: false,
    };

    return Component.extend({
        defaults: {
            template: "Shellpea_CDEK/cdek-pickup-points",
            selectedPickPoint: "",
            pickPointsList: ko.observableArray([]),
            isYandexMapEnable: ko.observable(true),
            caption: $t("Select Pickup Point…"),
            isListView: ko.observable(true),
            isSingleView: ko.observable(false),
            selectedPvz: ko.observable(emptyPvz),
            pvzToSubmit: ko.observable(emptyPvz),
            isLoading: ko.observable(true),
            mapHandler: null,
        },

        initialize: function () {
            this._super();
            this.pickPointsList.extend({ rateLimit: 300 });
            ko.bindingHandlers.map = {
                update: async (
                    element,
                    valueAccessor,
                    allBindings,
                    viewModel,
                    bindingContext
                ) => {
                    if (this.pickPointsList().length) {
                        element.innerHTML = "";
                        this.mapHandler = await this.initMap(
                            this.pickPointsList(),
                            element
                        );
                    }
                },
            };

            this.showDetailedInfo = (currentItem) => {
                this.isListView(false);
                this.isSingleView(true);
                currentItem.buttonText = "Получить здесь";
                currentItem.isSelected(false);
                this.selectedPvz(currentItem);
                this.pvzToSubmit(emptyPvz);
                if (this.isYandexMapEnable()) {
                    b;
                    this.mapHandler.setLocation({
                        center: currentItem.coordinates,
                        zoom: 18,
                        duration: 1000,
                    });
                }
            };

            this.showListView = () => {
                this.isListView(true);
                this.isSingleView(false);
                this.selectedPvz(emptyPvz);
                this.pvzToSubmit(emptyPvz);
                if (this.isYandexMapEnable()) {
                    this.mapHandler.setLocation({
                        center: this.pickPointsList()[0].coordinates,
                        zoom: 10,
                        duration: 1000,
                    });
                }
            };

            this.confirmPvz = (pvz, event) => {
                pvz.buttonText = "Выбрано для получения";
                pvz.isSelected(true);
                this.selectedPvz(pvz);
                this.pvzToSubmit(pvz);
                this.saveShippingInformation(pvz, event);
            };
        },

        initObservable: function () {
            this._super().observe("selectedPickPoint");

            this.isLoading(true);

            this.showCdekListPickupPoints = ko.computed(function () {
                const tariffToPickupPoint = ["136", "138", "232", "234"];
                const tariffToParcelTerminal = ["366", "368", "378"];
                if (
                    quote.shippingMethod() &&
                    quote?.shippingMethod()?.carrier_code !== undefined &&
                    quote?.shippingMethod()?.carrier_code === "cdek"
                ) {
                    if (
                        tariffToPickupPoint.includes(
                            quote?.shippingMethod()?.method_code
                        )
                    ) {
                        return this.getCdekListPickupPoints("PVZ");
                    }

                    if (
                        tariffToParcelTerminal.includes(
                            quote?.shippingMethod()?.method_code
                        )
                    ) {
                        return this.getCdekListPickupPoints("POSTAMAT");
                    }
                }
                return false;
            }, this);

            return this;
        },

        loadScript: (FILE_URL, async = true, type = "text/javascript") => {
            if (typeof window.ymaps3 == "undefined") {
                return new Promise((resolve, reject) => {
                    try {
                        const scriptEle = document.createElement("script");
                        scriptEle.type = type;
                        scriptEle.async = async;
                        scriptEle.src = FILE_URL;

                        scriptEle.addEventListener("load", (ev) => {
                            resolve({ status: true });
                        });

                        scriptEle.addEventListener("error", (ev) => {
                            reject({
                                status: false,
                                message: `Failed to load the script ${FILE_URL}`,
                            });
                        });

                        document.body.appendChild(scriptEle);
                    } catch (error) {
                        reject(error);
                    }
                });
            }
        },

        initMap: async function (pickPoints, element) {
            let self = this;
            const response = await fetch(url.build("cdek/checkout/yandexapi/"));
            const yandexApi = await response.json();
            self.isYandexMapEnable(yandexApi.enable);
            if (!yandexApi.enable) {
                return;
            }
            await this.loadScript(
                `https://api-maps.yandex.ru/v3/?apikey=${yandexApi.apiKey}&lang=ru_RU`
            );
            await ymaps3.ready;

            const {
                YMap,
                YMapMarker,
                YMapDefaultSchemeLayer,
                YMapDefaultFeaturesLayer,
            } = ymaps3;

            class MyMarkerWithPopup extends ymaps3.YMapComplexEntity {
                _onAttach() {
                    this._actualize();
                }
                _onDetach() {
                    this.marker = null;
                }
                _onUpdate(props) {
                    if (props.coordinates) {
                        this.marker?.update({ coordinates: props.coordinates });
                    }
                    this._actualize();
                }

                _actualize() {
                    const props = this._props;
                    this._lazyCreatePopup();
                    this._lazyCreateMarker();

                    if (!this._state.popupOpen || !props.popupHidesMarker) {
                        this.addChild(this.marker);
                    } else if (this.marker) {
                        this.removeChild(this.marker);
                    }

                    if (this._state.popupOpen) {
                        this.popupElement.style.display = "flex";
                        this._markerElement.removeChild(this._beaconElement);
                    } else if (this.popupElement) {
                        this.popupElement.style.display = "none";
                        this._markerElement.appendChild(this._beaconElement);
                    }
                }
                _lazyCreateMarker() {
                    if (this.marker) return;

                    const pinElement = document.createElement("div");
                    pinElement.className = "my-marker__pin";

                    const beaconElement = document.createElement("span");
                    beaconElement.className = "my-marker__beacon";
                    this._beaconElement = beaconElement;

                    const animation1 = document.createElement("div");
                    animation1.className = "my-marker__animation";

                    const animation2 = animation1.cloneNode(true);
                    animation2.classList.add("my-marker__animation-delay");
                    beaconElement.append(animation1, animation2);

                    const markerElement = document.createElement("div");
                    markerElement.className = "my-marker";
                    markerElement.append(pinElement, beaconElement);

                    this._markerElement = markerElement;

                    pinElement.onclick = () => {
                        this._state.popupOpen = true;
                        this._actualize();
                    };

                    const container = document.createElement("div");
                    container.append(this._markerElement, this.popupElement);

                    this.marker = new YMapMarker(
                        { coordinates: this._props.coordinates },
                        container
                    );
                }

                _lazyCreatePopup() {
                    if (this.popupElement) return;

                    const element = document.createElement("div");
                    element.className = "popup";

                    const textElement = document.createElement("div");
                    textElement.className = "popup__text";
                    textElement.textContent = this._props.popupContent;

                    const closeBtn = document.createElement("button");
                    closeBtn.className = "popup__close";
                    closeBtn.type = "button";
                    closeBtn.textContent = "✖";
                    closeBtn.onclick = () => {
                        this._state.popupOpen = false;
                        this._actualize();
                    };

                    const alertBtn = document.createElement("button");
                    alertBtn.className = "popup__alert-btn button";
                    alertBtn.type = "button";
                    alertBtn.textContent = "Получить здесь";
                    alertBtn.value = this._props.value;
                    alertBtn.onclick = (event) => {
                        self.selectedPickPoint = this._props.value;
                        self.saveShippingInformation(_, event);
                        closeBtn.onclick();
                    };

                    textElement.append(alertBtn);
                    element.append(textElement, closeBtn);

                    this.popupElement = element;
                }

                constructor(props) {
                    super(props);
                    this._state = { popupOpen: false };
                }
            }

            const map = new YMap(
                element,
                {
                    location: {
                        center: pickPoints[0].coordinates,
                        zoom: 8,
                    },
                },
                [new YMapDefaultSchemeLayer()]
            );

            map.addChild(new YMapDefaultFeaturesLayer({ zIndex: 1800 }));

            pickPoints.forEach((point) => {
                map.addChild(
                    new MyMarkerWithPopup({
                        coordinates: point.coordinates,
                        popupContent: point.name,
                        value: point.code,
                    })
                );
            });

            return map;
        },

        formatPvzType: function (data) {
            return data.type === "PVZ"
                ? "Пункт выдачи заказа"
                : data.type === "POSTAMAT"
                ? "Постамат"
                : "";
        },

        getCdekListPickupPoints: function (type, postalCode) {
            const self = this;
            const list = [];
            return $.post({
                url: url.build("cdek/checkout/listpickuppoints/"),
                dataType: "json",
                data: {
                    postalCode: quote.shippingAddress._latestValue.postcode,
                    countryId: quote.shippingAddress._latestValue.countryId,
                    type: type,
                },
                success: function (response) {
                    for (const pvzInfo of response.pickup_points) {
                        list.push({
                            code: pvzInfo.code,
                            name: pvzInfo.name,
                            workTime: pvzInfo.workTime,
                            coordinates: pvzInfo.coordinates,
                            fullAddress: pvzInfo.fullAddress,
                            latitude: pvzInfo.latitude,
                            longitude: pvzInfo.longitude,
                            cash: pvzInfo.cash,
                            cashless: pvzInfo.cashless,
                            phone: pvzInfo.phone.replace(
                                /(\d{1})(\d{3})(\d{3})(\d{2})(\d{2})/,
                                "$1 $2 $3 $4 $5"
                            ),
                            location: pvzInfo.location,
                            type: self.formatPvzType(pvzInfo),
                            buttonText: "Получить здесь",
                            isSelected: ko.observable(false),
                        });
                    }
                    self.pickPointsList(list);
                    pvzModel.setList(self.pickPointsList);
                    self.isLoading(false);
                },
            });
        },

        /**
         * Save Shipping Information after user select pick point
         * @param {Object} _data
         * @param {Event} event
         * @returns {void}
         */
        saveShippingInformation: function (_data, event) {
            if (quote.shippingAddress()["extension_attributes"] === undefined) {
                quote.shippingAddress()["extension_attributes"] = {};
            }
            quote.shippingAddress()["extension_attributes"]["pickup_point"] =
                event.currentTarget.value;

            const isGuest = resourceUrlManager.getCheckoutMethod() === "guest";
            const params = isGuest ? { cartId: quote.getQuoteId() } : {};
            const urls = {
                guest: "/cdek/:cartId/shipping-information",
                customer: "/cdek/mine/shipping-information",
            };
            const serviceUrl = resourceUrlManager.getUrl(urls, params);
            const payload = {
                addressInformation: quote.shippingAddress(),
            };

            fullScreenLoader.startLoader();

            storage
                .post(serviceUrl, JSON.stringify(payload))
                .done(function () {
                    fullScreenLoader.stopLoader();
                })
                .fail(function (response) {
                    errorProcessor.process(response, globalMessageList);
                    fullScreenLoader.stopLoader();
                });
        },
    });
});
