/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./src/scripts/dashboard/components/App.js"
/*!*************************************************!*\
  !*** ./src/scripts/dashboard/components/App.js ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _stores_connections__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../stores/connections */ "./src/scripts/dashboard/stores/connections/index.js");
/* harmony import */ var _stores_selectedConnection__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../stores/selectedConnection */ "./src/scripts/dashboard/stores/selectedConnection/index.js");
/* harmony import */ var _stores_searchConnection__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../stores/searchConnection */ "./src/scripts/dashboard/stores/searchConnection/index.js");
/* harmony import */ var _stores_settings__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../stores/settings */ "./src/scripts/dashboard/stores/settings/index.js");
/* harmony import */ var _stores_logs__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../stores/logs */ "./src/scripts/dashboard/stores/logs/index.js");
/* harmony import */ var _pages_ConnectionsPage__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../pages/ConnectionsPage */ "./src/scripts/dashboard/pages/ConnectionsPage.js");
/* harmony import */ var _pages_SearchPage__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../pages/SearchPage */ "./src/scripts/dashboard/pages/SearchPage.js");
/* harmony import */ var _pages_ModelsPage__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../pages/ModelsPage */ "./src/scripts/dashboard/pages/ModelsPage.js");
/* harmony import */ var _pages_VectorsPage__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../pages/VectorsPage */ "./src/scripts/dashboard/pages/VectorsPage.js");
/* harmony import */ var _pages_SyncPage__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../pages/SyncPage */ "./src/scripts/dashboard/pages/SyncPage.js");
/* harmony import */ var _pages_PromptsPage__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ../pages/PromptsPage */ "./src/scripts/dashboard/pages/PromptsPage.js");
/* harmony import */ var _pages_LogsPage__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ../pages/LogsPage */ "./src/scripts/dashboard/pages/LogsPage.js");
/* harmony import */ var _GregiusLogo__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! ./GregiusLogo */ "./src/scripts/dashboard/components/GregiusLogo.js");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_16___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_16__);
/* harmony import */ var _utils_api__WEBPACK_IMPORTED_MODULE_17__ = __webpack_require__(/*! ../utils/api */ "./src/scripts/dashboard/utils/api.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__);
/**
 * Main App Component for Gregius PostgreSQL Dashboard
 * 
 * Root React component that manages the overall dashboard structure,
 * routing, and state management.
 */





// Import stores (this registers them with WordPress data)

 // Shared state for Content/Sync/Vector pages
 // Independent state for Search page



// Import page components









// Import utilities



const App = () => {
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [apiStatus, setApiStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [activeTab, setActiveTab] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('sync');

  // Use WordPress data store for settings
  const {
    settings,
    isLoadingSettings,
    settingsError
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_16__.useSelect)(select => ({
    settings: select('gg-data/settings').getSettings(),
    isLoadingSettings: select('gg-data/settings').isLoading(),
    settingsError: select('gg-data/settings').getError()
  }), []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    // Listen for custom navigation events (e.g., from SearchSettingsCard)
    const handleNavigateToTab = event => {
      if (event.detail && event.detail.tab) {
        setActiveTab(event.detail.tab);
      }
    };
    window.addEventListener('gg-navigate-to-tab', handleNavigateToTab);

    // Initialize dashboard
    const initializeDashboard = async () => {
      try {
        // Check REST API connection
        const apiCheck = await (0,_utils_api__WEBPACK_IMPORTED_MODULE_17__.checkApiConnection)();
        setApiStatus(apiCheck);
        setIsLoading(false);
      } catch (error) {
        setApiStatus({
          success: false,
          message: error.message
        });
        setIsLoading(false);

        // Signal React load failure
        if (window.jQuery) {
          window.jQuery(document).trigger('gg-data-react-failed');
        }
      }
    };
    initializeDashboard();

    // Cleanup event listener
    return () => {
      window.removeEventListener('gg-navigate-to-tab', handleNavigateToTab);
    };
  }, []);

  // Signal successful React load when dashboard has finished loading
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!isLoading && window.jQuery) {
      // React dashboard has loaded successfully, regardless of API status
      window.jQuery(document).trigger('gg-data-react-loaded');
    }
  }, [isLoading]);

  // Tab configuration
  const tabs = [{
    name: 'models',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Models', 'gregius-data'),
    className: 'tab-models',
    component: _pages_ModelsPage__WEBPACK_IMPORTED_MODULE_10__["default"]
  }, {
    name: 'connections',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connections', 'gregius-data'),
    className: 'tab-connections',
    component: _pages_ConnectionsPage__WEBPACK_IMPORTED_MODULE_8__["default"]
  }, {
    name: 'sync',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync', 'gregius-data'),
    className: 'tab-sync',
    component: _pages_SyncPage__WEBPACK_IMPORTED_MODULE_12__["default"]
  }, {
    name: 'vectors',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Vectors', 'gregius-data'),
    className: 'tab-vectors',
    component: _pages_VectorsPage__WEBPACK_IMPORTED_MODULE_11__["default"]
  }, {
    name: 'prompts',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompts', 'gregius-data'),
    className: 'tab-prompts',
    component: _pages_PromptsPage__WEBPACK_IMPORTED_MODULE_13__["default"]
  }, {
    name: 'search',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Search', 'gregius-data'),
    className: 'tab-search',
    component: _pages_SearchPage__WEBPACK_IMPORTED_MODULE_9__["default"]
  }, {
    name: 'logs',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Logs', 'gregius-data'),
    className: 'tab-logs',
    component: _pages_LogsPage__WEBPACK_IMPORTED_MODULE_14__["default"]
  }];

  // Show loading state
  if (isLoading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Flex, {
      direction: "column",
      align: "center",
      justify: "center",
      gap: 4,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {
          style: {
            width: '30px',
            height: '30px'
          }
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Initializing Gregius Data Dashboard...', 'gregius-data')
        })
      })]
    });
  }

  // Show API connection error
  if (apiStatus && !apiStatus.success) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("div", {
      className: "gg-data-dashboard-error",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "error",
        isDismissible: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('REST API Connection Failed', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("p", {
          children: apiStatus.message
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please ensure the plugin is properly activated and the REST API endpoints are accessible.', 'gregius-data')
        })]
      })
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("div", {
    className: "gg-data-react-dashboard gg-data-react-app",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
      isRounded: false,
      className: "gg-data-dashboard-main",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
        style: {
          flexWrap: 'wrap'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
          level: 1,
          style: {
            padding: 0,
            lineHeight: 1,
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(_GregiusLogo__WEBPACK_IMPORTED_MODULE_15__["default"], {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("svg", {
            className: "gg-data-logo-svg",
            version: "1.1",
            id: "Layer_1",
            xmlns: "http://www.w3.org/2000/svg",
            xmlnsXlink: "http://www.w3.org/1999/xlink",
            x: "0px",
            y: "0px",
            width: "28px",
            height: "28px",
            viewBox: "0 0 32 32",
            enableBackground: "new 0 0 32 32",
            xmlSpace: "preserve",
            "aria-hidden": "true",
            focusable: "false",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)("g", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)("g", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)("linearGradient", {
                  id: "ggDataDashboardTitleSvgGradient",
                  gradientUnits: "userSpaceOnUse",
                  x1: "13.665",
                  y1: "11.2705",
                  x2: "27.9102",
                  y2: "11.2705",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0",
                    style: {
                      stopColor: '#00A651'
                    },
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("animate", {
                      attributeName: "stop-color",
                      values: "#00A651;#58C6D1;#F1667C;#00A651",
                      dur: "8s",
                      repeatCount: "indefinite"
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.021",
                    style: {
                      stopColor: '#00A85B'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.0699",
                    style: {
                      stopColor: '#00AF7A'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.1201",
                    style: {
                      stopColor: '#00B695'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.1707",
                    style: {
                      stopColor: '#30BCAB'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.222",
                    style: {
                      stopColor: '#46C0BD'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.274",
                    style: {
                      stopColor: '#52C4C9'
                    },
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("animate", {
                      attributeName: "stop-color",
                      values: "#52C4C9;#ED683C;#00AF7A;#52C4C9",
                      dur: "8s",
                      begin: "-2s",
                      repeatCount: "indefinite"
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.3275",
                    style: {
                      stopColor: '#58C6D1'
                    },
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("animate", {
                      attributeName: "stop-color",
                      values: "#58C6D1;#ED1162;#00A85B;#58C6D1",
                      dur: "8s",
                      begin: "-4s",
                      repeatCount: "indefinite"
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "0.3843",
                    style: {
                      stopColor: '#5AC7D4'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "1",
                    style: {
                      stopColor: '#F1667C'
                    },
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("animate", {
                      attributeName: "stop-color",
                      values: "#F1667C;#00B695;#EF4B70;#F1667C",
                      dur: "8s",
                      begin: "-1s",
                      repeatCount: "indefinite"
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "1",
                    style: {
                      stopColor: '#EF4B70'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "1",
                    style: {
                      stopColor: '#ED1162'
                    }
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("stop", {
                    offset: "1",
                    style: {
                      stopColor: '#ED683C'
                    }
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("path", {
                  fill: "url(#ggDataDashboardTitleSvgGradient)",
                  d: "M16.074,18.394c-0.616,0-1.233-0.236-1.704-0.705c-0.94-0.942-0.94-2.468,0-3.409l9.427-9.427 c0.941-0.941,2.466-0.941,3.408,0c0.94,0.941,0.94,2.466,0,3.407l-9.428,9.428C17.307,18.157,16.69,18.394,16.074,18.394z"
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsxs)("g", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("defs", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("path", {
                    id: "ggDataDashboardTitleSvgPath1",
                    d: "M15.619,0.289l0.335-0.026c1.325-0.102,2.483,0.89,2.585,2.216c0.102,1.321-0.882,2.475-2.2,2.584 l-0.146,0.012L15.94,5.096L15.619,0.289z"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("clipPath", {
                  id: "ggDataDashboardTitleSvgClip1",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("use", {
                    xlinkHref: "#ggDataDashboardTitleSvgPath1",
                    overflow: "visible"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("g", {
                  transform: "matrix(1 0 0 1 -9.536743e-007 0)",
                  clipPath: "url(#ggDataDashboardTitleSvgClip1)",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("image", {
                    overflow: "visible",
                    width: "43",
                    height: "24",
                    xlinkHref: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAC4AAAAdCAYAAADVV140AAAACXBIWXMAAC4jAAAuIwF4pT92AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAOZJREFUeNpiYBgF9AWMA2Xxj/dOCUAqngytCzkE9y1gGUBHKEAxqeAgiGAZYEeQDeAO//bOO4GR8Q/Qwb/p7giKHA51rMNQyZxMQ7VUYcIsZBiHaogPSYf/B+J/o2mcfg7/P3TSOAv2NP5/MLs5HlhJPhiKSQVcQaI6nHGoZs7/TEMmvw6XCujfYM+Yw66tMjQdvuA/AxMIDy2HcwltfQDMmA8HeeZcAMSOIBpHzUm32hPkkIUkqH8A7Cg/wFHlU1Ttk+0QKrRVUGpPujmEVICSoIEdZgVgh1mBkeE33R0yCgY7AAgwAPoFPF9RAZEaAAAAAElFTkSuQmCC",
                    transform: "matrix(0.24 0 0 0.24 15.6182 -0.479)"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("image", {
                  overflow: "visible",
                  width: "132",
                  height: "132",
                  xlinkHref: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIUAAACFCAYAAAB12js8AAAACXBIWXMAAC4jAAAuIwF4pT92AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAG6JJREFUeNrsXXusbFdZX9/aM6flaVvff6lBYwzyUASlcikqAdNEDVip5Aq9F6glgDwUwiNQEKlAgFShVAtCW2pBpBSwhBYR6eWSFm4ByzNqJMofRqPRFkQp98xen+t7rcfee+Y87pzHnLtX7s7M7Nl7ztxZv/X7ft9jreXd2MbWaZPT4T/5b//+HJz4k86777gJzJyHkw7czDnA+G4bX4d4fNudefbfwgiJAwiKL379Mpz6ddfETp9GINABEQTO4cDVrRtRcABB8Zl/eROCiyM9dvokPlJHUwPIIAjYuCYyBD+Ph09QaOJdAgx00xENqwyKY197C7MBdyYSAKhTySB4t6agwAgVfoNAEM1DiADg/268HiNARoY4AKC4+e/fhk3s3AZaHu8hTJz3/c7FCIxZfC8+uAbb+FwY4Izmf+O1wh7ELMiPrrg/jGhYFVC8/6vX4RpT/7qaBXCpRweUAnW4dX6rZoNYpMVpFJltAg64fL+Aox3RsJ9B8Z6vvBsnkRUAZfQGAO7IFpMoSBoha4ZWn3s38W3BHFA8b4QlcBLBFT8IZ260I/scFNd9+S/RqD31PknB2NE+dugEMs2TZsAA0YRgPeJBrmdQVWIzfoJqjFpsKmCwGdGwn0Dxzi/dSPLPzaJqnIJ0+kw7lpUkjW9vlp80Q+zUBhkyGTrCCuuRBdZgvSc2G2YPYRBhmYJN4t+CkTFS83v9Bd7+xQ9gi6Xca+KoFrsvY1++Yhsadh5NLwRiCtcklqCRjh2x2ZKTGq9r4/OT7Zn8WfQemQ95XomRse01U1z5hZvQs2YIxah2rBt82UGkJzCI28nGRcUjDvciatQhhaciAJokMJtBQUn6YhSaewiKK+78MKbRDpG24ysfO426xWvMgWMK0Z2kuOQadyiNaoyvJywOAfT9yA5TMiXqmopXEdiE3Nvf0xObJEjp3ln0RKYU7u5oinvu+kUcQ927bD4u//zNuI7kRYBIBe4MrL4GZyMQEpuv07Vg13VYoccWOdawrsKxFJueAWaxCjvfdB7HtmugeP3nPobYU/zCFrlLIRn4ttIW8lUFBPPMRq0rYAA4pWlBhsWEBScJTTeaj90zH5d99uPoQXwD6vSGRz6xBUUnUUe1j3QusBBtgcwWWVvQPdKlZFIm0XTQi1l0SXw0B5PChEABgBzZnLHYJAB6L6ZIPI5QAGYt3vHtERE7zRSv/uwnYvf5pPQpftCmPwkyWnVot0G+DscjUd+j15hNgZmQWXzd4kTv85ULUbqm5m7atTNcq8Slo/Q5iU8YQ9y7AopL7ziGM/RZFygAiAHWC5ovzUgbah+R7ofCpBDDEGCILVonngh9Hh2UCzEx2TVHDQeyQuGBBHVLSbgCB7bGtsOgeMUdxzG7hJDiCpUkjB0hotN39IUvtAWXwAyyBRsBzDkONT5JXzCMSLByhhQSWEDFaBiDE7sHiped+BTW+QZInYvR/qOObDYj/AiuCl7pc0SniSwoxKawhUMBgkUhycPwA1lO+hvrYcouaBUcYxE7dT3hO3ogywfFS07chtKxGmvQVpoRHvkccSz1RQke6GkL+0xiC+BRL4KTtAX9nfW24c9MXgjziPytHAH1HBUt/9tlCn1sOwCKF564HaXSQURlcH1gJIagA1R4sr7I7JDZIgNjNidZVbqbpitQz2PvOp+Ep0UvyzC5OcJjW5JL+oITn4mAkJ9YHonioy0HCQpRFiIUcQJijDUx+kkQEhAavje4lkLbkQ4a7+rRHz/zZLxiGu8LIbh1P4nuaehWy3TiFARAiZgSz/zA9791JIadBsVzT5zA4HJ6SWhbgAHaYTN+1SbGoOwnMQbfw3c3zAtYprrBM0CkM1Hvc3yOnk9NdIKw03dmU3efyT1VbJM44cE//LIRBLsJiuecuCMBgs0GSUygOCEqBHxKZrkOMIQ0CDSeWWHiJcpA7xEIJODli4BW1iYEKj3lvo1r7j5wT3JHD/3I80cQ7BUonvWZz6KAwKXRjFoqN4sdTZXVrULBKWOEAhjscVCEsbTjGs00fbFObMLYCClZlmIRBAz9+yejh/ErP3F0BMNegyK5c9oxrQIjCUvntYAe1TiEOiuZ9EUT9cUsgUTKrYkNMMUuGuYLiV4QW1DmlLyQCx741BEI+wUUz/z057nnSmDw6I/oaLTcHuK5Flw2JWwygrKFY4YwfXEyXjWlq1RfkDAkkDSAg3//8E9eOIJhP4Hitz/9dzg828oVcQbH4ChNicUKKDZQeiUGDK6NIEBREMI71hRUcNNwHYXnMr2nPeiJIxj2W5ziYmYI0RBSE2GJK5mjFYo6ibYT1ZxpFICjk5g9hDK4FarIZv3VLn7wE0ZA7EemKCkdNIvgqzkYtc5glogmgQDR9UpEfPY9Eo5TJBGK7tkPPX8Ew35liiO33zloMyzqyEAZ6D4CBs3b4NC2FeE6p8zSj3qeZGMj0c/n/dQIiH3LFAYI1v9k54HLVArGAHYnfWQI7zXjiWAVEwwKdlkpZF24qy4V6Yu+sPbShz12BMN+Z4qyPDIUWU3JYGaAcLg65CQWFoU0pDFmIGX7s5SwInYQMzRTxrj0Z35hBMR+B8WR27+ABoyUsOLKJp+DUM5VwECEXlGt3bvuJDvaukKk6me96uHnjYBYBfMhU/0ti4lp6n8yJ4KIZFKSOemIUJm/kc0J6LwuFpbx8XUPf9QIiFUAxUW3ZZYoRaSN+gochYlpiuynAQPsBgUWBbq8Lhly+SMeOQJiFV3SLmN0wZLYoRPYMmDItajSVICxHjXGFY94+AiIVdEUT40sgQPAkJPAAAEoJ/X0hWglQM11hSxER0CsGFOgjWg3XK5mLAGqFcpzVjRjzFGaE3I1AOYENca2f5niKbd9EQ0YqiOrxy5ziFnJzDHPhUWdEESMceUjHjaiYlVd0nnAwDlxjNxg0KRQ+9Of++kREKtmPkIHLWUpPCSlMGxShDHMOwFXFuKQSbnmkQ8dAbFqTHH4ti+hK2IN85jD2AIXmJXFTDK2lXRJS2CUrmZ3Ek3JHjCHOYgxrj13ZIkDoSlKgGyXPQwcY1tBphDTsTE4Sjj4DjBgjuK49tyHjKg4COZj4wapeqqc9+Eq3hg54jQDxWL9YQB517kPHnGxippiM6ZjGfpjbKcRU8wDx/XnPmhEx0H1PsZ2GoLCD4ahxnZamw9fZDmw0gTbX9ZjNB0rDIqjt93JE/bAqqRQZn1zgUwqzYWqkGZcM+qAg0KXMWdUBFuIDJ0uByBgAUANZ4Ne7yqweE2Lj+2AgGKaVp1xPKMrQOlB5FVxg04cpr5nECCkZY69y6Dpxi3GtpJMEZgNbHkAjz4BwxYxLJWpvTrJC6fLIiLe4hMKmrGtOCgmPOod1+TPUBYS4dX1ixpLWyGXlzSlBc0A3Jm4Livx0zs0qUfXnqJ1qa4bo5grzhSoC4oEl5cNgDzvkxYQCbriXKPPnb7mZc9RlhSZ8JoUeRvIsa0wKBruRKj0gGdtIavIUO+v4czNaJZ4qBcxDTpvvFGhaQAZ2wEQmrzWtRbgNlo0M6WVMXXijpxv1UUVd9TAM6nMTL1dw9hWFBSOTQAOls21tHUCT94JAgwFUK7FlNnm/KzzemwrrSkUGiDPgy5NBGZaynIqcGnBs8YsibmzYQyUHxhQSKkMjfBiYrCqDId1mb+xANreGgqkMkYxtoMgNDEnw/rl/PXufb1zFTWMPHGAmCJ3KVQAyDDBzrmyQQcSI2PsXvvP73s6pkVpbcKVPS/PbV1oluktXzmceV8e3zvXbdB5HNsuNK/DMWU0F51zZXe7oksrEA1UXs3bT6tzvvQwAPrnxrY7zUZg06Pz4XMlGKrrMs9PXAEmXKAJ/AA00gShEQx7KwBsnmdw/Tmf1j8yd3M+lcMc7wNdf9GReeBwrk6OjW0P9MQPPR17nTMZ6JQAltnsm4/Q6UjE0nzg3ILNSbl3eJr0I23qgu72g72M6th2Q08MnPNzLH/JIn4AGAogX34OJcPomOrhHSZA+CC7f61hy4urT/Qgd3aKMw6JN3wEqbkY28636SwerXNr8ThjJo8TOpC3w0hoGAKCHwbS9/7rO0BS52Hm0Pue+SgZIm8njKxBGi7Zkz9qa2DaFWeE2dhhu6Ex70Ur4ovHgElPkNfRSn8xC7R9z4PMyQTzc+s97eOJdTgU0YVGM522UezESScTBmYRPJMQUrSTELKWMqcjQ+xqa3ibBMpcOpjpaJ+ibMxMuawgyxLS0MdSZHr1Ljzk8HXI/Sfmg0a9HpQip7ZG5iJ+0jSsRyZCfk57gk3bWXzUvTnoCPU8cxKtZEqu+Nj7R4TsdDsj/v5nSG0tg2GKAhQRew7ic5gKUECXQGfbQOcneq25npTIUoBo5VVdFEMdL5nTciunrgeTBeVE9w5zxCAg1Vl+hMQugKLVLKSqxZNeYg401oMAQ4a+AIB5vwVhlYn6myWruAIUvm3nsFMbTU5T+TgTzZNwvEpBICzixpjFLrb/evyTZJ5FZAFcnwkw7hVUI0jRFD9vlUF4IXSvrFIITWOQ73TiFHX8Ikhq3OpvyaPosAAUk8nIpKTSfxWeZFLGFRF3Wk+EVOcAU8/9w+aA9uha426Lz710fqu92yhoko7QlZJVl1Se7gse+2tArODjMUFxRem5Z9c0+hZRX/C2kBjE5dRQVwMtA4GAQdc3Cjy5Ft07P3r9SBs75nqoWSDzsTYTb4I6faLabhrBshb7Zi0O0Kg94N7kugZxYekeBpUCiT8D5XDVtEEJPHkMFQtgSpOFNMfDpGyKR4DVWYAgFlzloo5tBxrFJLSAmkd6o7+7D9KrraxuzK95O0hggOA6MmDoka8js6Os8j23Xw8VKCY4K+hDOx1k4xYRkWJSGg+ynaTWbTpzR3Xz2dFs7IKeeNKv6v6cWiY3LWINZg68ehYWmPISz4A1AQ4vZ3kyvp4E2U8WQj9QartvkEngKYLqbspEIdEIrDVCEFYIIW1CCzC43Ti3q0cTsnzLEU0ANGoCyKtQMwJeTQKxiJkDczs9Ji9EzsV/Z4q5gWlgU9MDxSWPuxBIF7BIVBCAmgUPRTGu9v4iICQBiqFyXce2LFQQCGaxI2cMDiRA0IinEHeDKTiVgDMNIiQJLNMiLgH6WawrcI73UfRwVZFtjLCZL4w5hjFakuW3/z7yOHQ69cKFhnMdSeudnAg4gu6WkCqvIkBoG69gGsOpHtGUeluHEqp8WhnZ3CwjVKyAac66glDmpF/30WtHE7JM0zGRwzWU12iTHqD3WC9EMyEsgnwQG6BXMxMZAsyEmGsaH7/7IzfAIFPY8IYtMdkwK3A8A9yYDlkmS1z8GOR5eLpBXxKSIPGkxB6gOiJ4Bg6/TV5GI4U22Kjo5LXTW1ctM9Bliqc9/vDGzgPWzFCyCDNDwRYOs4B99y1Xj/A41eYlaogggpIZgxlBmYPFpLAFP0bdIZqB9/4VHWK5kXgNmlBtcD4oNmSEjl6AHjOoQEWsYh0NeSzxeO/NfzYCY7ss8cxHY/YiAs/cJXDwntBe+4RMA3sTmi73al6aWQEQ8VyAgKWMcs6NH6i4YLJZ0VjGL+rAVVHQj3mKACRXFoRRYCy+OaWm0zbTfItUsa1JBgIGi0oJf7Np4O3DtTbTa7DLPA4EDTv0eaF35ujjDsOQFzHMCpjyICYqzYRMQlBQZ0DQ17zhI1eNyNgqS1zyqOGJNqAVVsQc0MrhZ8oeKkiJJbqpcmIJOs/Rz35B1GSuN9E7hxlhULMFGIqdINQHBYwWBIMyiNf8ydi2qiUg161YOsFhzRwm4pzLa4eoEAU/k35rZCdYh7lA6pzrboYNmYLakcc/BbqehM37EnZABYR1dN5kDhQQDo1FclTUK9g+9OErRrbYLEs86xAmYPhyLl6x006m65wog0J3MIOIEGVRagGuOUUvGwrNFHeA7F1InaYeQcPjlFUNOYLpdZ09Og+6UzHHQJxUdH34pj8agbFBu+vZhxC6/n4XHJ1IcwaIy65p7ToyYChmcc61t8CWQHFRZAvzIgwMBgTvCmZwygy6eGKjAJHrWwbDJEiVty+AQ/fdctMbR2DMaXc/9xBaDMJSDtW8zAoYQU1CKO19BkaHOZLLOpcINmSJDAYAl8Wj1W1CSKbC521pRVu4cpJy2u5eWIXujZ/z1x98/QiMeWEAAkPjUoCqt51rz6Q4DV2XwBgwK2pStgWK3/rlIym+mfRCcjlbXRXPAJ09DF5YDWtzAWxGggJJi3hQADK2Dks879DwQCnrVBaalJB396t0R27nvP1W2BYo+LOChKsNEMkV1YVWbfT7pBsEDEPmgiYUMSBCm8BFX+DWG18zsoUB4gWPllHWzGeMijVKs+K7uzJ15vqWzLHQQmzQDp//NCgBYegTFzOzQ12Nhal205kIpddeZpplgWqlfMEdv+FVpz0w7nrhIayUJfTNSSqamVfdVrGGGwBGZImrjsEpgSK7pKodEjjzazYD+kg1mx6NHdT70KIdqho3s0HX2aQiY43bbrj0tAXGXS86ZD+mS8Dw/chh0hQdYABsYFIc9jeMPRVQ/Ob5zwCbLwal+FS302OhI0q31PSDzkMFE6RkXkJI93B+BCUwduJ9Lz/tgHH3iw/NX/zHzxGgUAOj0hoLwHHOVcdhKaCgdiEDQxdmR9EPjWkHFzrmQg7RIq2YikIVl+zAwS2UVfjowAiWO/7ixfi5977otADH3S8ZCGErW6ANf+8G6xkSMJoOawzZlZ5ZWQIozIyIqchl/s6il4W5cGwySGTOUsFOuhdD4Z2I4PRpHoIKUi5ORnfne373wALj7pc+CukwAdiLQ2gRTM+UuDnmpMMSQ8A450+Ow9JBccH5l4CYhfnmIoW1QzYxk8ojUR2Bdo94OFYQbHUa4vmg+/L1z8ODCIheR3aB0WGNBIxmg3hGFyP6mZsFxJZBkf8POGguDBAWBrfrUScHJbOh75nZyAwRNCSf3Vy68ivXPxe/+ufPPhDg+MZLB2IQmINNsLD2EXoicyNz4tzWa2W3VVt7001vxhIQTgNQEqiy3YNadlpLk2GAsJX/UyAsqEnCNgMCs1nyOnuNrvvxi962kvXA33j5o9Hmzjg3sL5YlaIAh+U+f/YYQEMMxfIBA0OF7y1Whzj7yuOw46CgRgktMFNBCA9FRFNnnUMyFZkhGrtH55Akc2R6xPSKAsmF7OXQ9EXdn8j92EVvXwlwfPPSQ4hWAxnqBWkHF57DAVCU73WBMQccBoyz33oc3G4whbWb/+pNWALCPIsc4g4VIOQ1zSGZJTAkYCk70Gd5W1mLGYTrz9V1Rc21SIURvf7RI+/cl+D45isPYe5ILTwI/VWK5zKGAcN1GCOBYmPGOOvNx7f125zyD3rLh96A1vliQqQm0+ovTHxa6tz+F5JQIx0hS7A0oU0RTkuuudDq3mRB9xmx9XV0SzvEqsTvAUev3nOAfPPV52k/Yw2COcCYyxaLTIkyT8+UdO7dLihOeVtr0wpeJww1oTYZ5knYpJQaEAoATZaJqVBXVwNeBipBMFQ/ZFXzGd/62tVH2Wg/4Og1uwqO/7nsPMyd11l0thtBtNXo0k4JsHD90sFhrC48MmuY+MTqc7cLiKUwBbW/+eAfYhOK9HhhMkxU6v9C33OVsHQuJO/FwOH4mlk2G0VBcFpKKdSgsBFqrEPxjkl70v3gxe9bKki+9frzkmnAMKQFVFCWYeXWLtskWwyZEhx4b0BjnHX58VP6/y7tx/rEjZdhFxQiFrPbKYBuU4CtSdlSTNtQGSgkh6LJM2aSwmw4nAsINjekRUBMDm9mpYXELmoZjpkEYyYxT/f9nY9Xv8P//bGagHrlhQ4A6pTCIChKE6LASPHs7ZqRDTTGqQJiqaCgduuNf4C+A4g0LQAtONUWZgOTFyI1FjUgGDwhVDsLOCviWcQSUIDDWWRV5q3Qmp9g+sUYpVu1VHa8c9UoxXKh0pItbPMTvqYARWlShrTFqeiLDjDOuvyTS+nPpW729ZgnvgJKs0Hha0urp1I+m+2WTIWtnGP1qZkhTFyWYfYeIIZ+PSxMjeZWZEejkK8KxXNcPGSqidfzQs2pPtLlCbjdcikNLoHDwTwEbHaM9vIauDRALB0U1A79+ivBq0agkYpo0UxXLIWkQyZg2vmYzUvAuQQGZYd2f6Bi94KqA3WZpkH3D7YX8gub0ITQq5vb4NeHDUPIdcSz8/7Zbzy+VMbfkW0Bf/6CVzNjJEDwyJfAUw53uzyFPo3CUFWLmwtqg3k+S9hvJvfyINTr0s6HocizKJv4wd2NBiKJboApltL6wNyQLTqh8LPfcHzpntaO7RX5s7/xGsgmA5KO6A45Y4kMCNebXpimLuJ8lkiflVijwxBeU9FDvQpzXsMm+rNYsmH4mgUmZE4PDAID+sDYCUAsXWjOa59770vQ6jJrYWmu5SwJUp8WfM+BKuvYbrAq71OVBaYz3dITmYHXAKXXOSYSNiU0S7EJpUPR8UZKL2TTgrNrkxZFO4v3z3rdp3as73ZlV9mHXfg6yIDIYjON7mqkDyURs6vbB8SARkgiU3Zh5h+fFqQHqCgGTODiNofMhu/DJtlia/pmJwGxa6Cg9tAnvxFyFxdpdB7hLpmNvLF2HZfYsGnnGkv4tHdqUUEeyoADqjcHy+XLwgtJghO22BML9MVOA2JXQUHtIU9+E6S5qNQpwdazCAUTqLiEmlIHBWYRm6hoQ8PLvcnMULMKbOSSbgkIm7ioV0BTMiXMuUvOn/XaTwEdu9FPe5ZAosIZy29ApReyx5FC2ugWmA7kqYqys4CYJtYRGh+xqKnNQwGrFAshFQQNaooBL2RbuqKKcA7ri5Ratx0CO0D9rtce39V+2rOd6h94+M0AUM6DHQKEG/RGeqM7dPfT9rworC/i08P5hQWaYoFbijDfC9nO8BtkCxAw7DYg9pQpyvYP77oEh0CBpjMWsISFs6WcL4e2XZo+kKvPE1OEmd4bClCEDUExGO7uprU3ZIsNPBFjh9d8cs/6Zl8VqPzjtc+QFYHRDSe+5oDCF6ajBIWJTE7nh6JCTCu4tguKynwsERR7CYR9Cwpr/xTBYXUXi8LaZQKs0hNYZFhtMhJmRskZ0rBpPdFlikFgbFNX3P/3j++rftjXdY5SNDOfJZpymWfMgrMChW1ToSFuyY7OtiwytwwKtzFb3P9Vn9yXv/9KFL8mcBQpco5BdLwOsOxqAQpb6sBX0cxiJvw8UCzwPuaCInkTfVCUwLj/pcf29e++UuXyX7vmKC4yHfNFZl4kZdgdHdAUpwCKIRNyv5ffujK/9cquqf7PVx9B2AwoIE9mhlRxFTrex3KZojQh93vZsZX7jQ/EQvtff8dhFJFpE53bKrw9LDJxsabYBiju++JjB+L3PLC7L/zHVU/ADIpCZG41mjkHFPf5vWMH9rc7bbfk+NZbfgk3AsW9n3/stPx9/l+AAQCf4MY5PuIEVQAAAABJRU5ErkJggg==",
                  transform: "matrix(0.24 0 0 0.24 0.25 0.2622)"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("defs", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("path", {
                    id: "ggDataDashboardTitleSvgPath2",
                    d: "M26.924,16.231l0.007-0.247l0.004-0.133c0.04-1.33,1.151-2.375,2.48-2.334 c1.325,0.04,2.367,1.144,2.334,2.467l-0.009,0.342L26.924,16.231z"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("clipPath", {
                  id: "ggDataDashboardTitleSvgClip2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("use", {
                    xlinkHref: "#ggDataDashboardTitleSvgPath2",
                    overflow: "visible"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("g", {
                  clipPath: "url(#ggDataDashboardTitleSvgClip2)",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("image", {
                    overflow: "visible",
                    width: "22",
                    height: "42",
                    xlinkHref: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAB0AAAAuCAYAAADUfRIMAAAACXBIWXMAAC4jAAAuIwF4pT92AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAJJJREFUeNpiYBgAwIgu8Fo0RQFIJVDZngeir+csgHFYsCgAWVpPZUsPADHcUqaBCN5ha+kDIF5Id0uRE9EIjlNoHo2nt09pUTCMnCwzaumopaOWjlo6aumopaOWjlo6aumopaOWjlo6aim5gIVsJ/yH0ow4xP4Taynj/wdAspEoS2EGM+IQA+F/YNEHDCMWAAQYAMmbF57TnuUhAAAAAElFTkSuQmCC",
                    transform: "matrix(0.24 0 0 0.24 26.9233 6.3472)"
                  })
                })]
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("span", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Gregius Data', 'gregius-data')
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('The orchestration layer for AI workflows in WordPress', 'gregius-data')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TabPanel, {
          activeClass: "is-active",
          onSelect: setActiveTab,
          tabs: tabs.map(tab => ({
            name: tab.name,
            title: tab.title,
            className: tab.className
          })),
          children: tab => {
            const selectedTab = tabs.find(t => t.name === tab.name);
            if (!selectedTab) return null;
            const TabComponent = selectedTab.component;
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_18__.jsx)(TabComponent, {
              settings: settings || {},
              isLoading: isLoadingSettings,
              error: settingsError,
              apiStatus: apiStatus
            });
          }
        })
      })]
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (App);

/***/ },

/***/ "./src/scripts/dashboard/components/DatabaseSelector.js"
/*!**************************************************************!*\
  !*** ./src/scripts/dashboard/components/DatabaseSelector.js ***!
  \**************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ DatabaseSelector)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! prop-types */ "./node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_3__);
/**
 * DatabaseSelector Component
 * 
 * WordPress-native database connection selector using @wordpress/components
 * Replaces custom HTML select with accessible SelectControl component.
 */






/**
 * DatabaseSelector Component
 * Allows user to select the active database connection for content, schema, and sync management.
 * 
 * @param {Object} props - Component props
 * @param {Array} props.connections - Array of connection objects { id, name, is_active, is_default }
 * @param {string} props.selectedConnectionId - Currently selected connection name (not ID)
 * @param {Function} props.onSelect - Callback function(connectionName) => void
 * @returns {JSX.Element} SelectControl component
 */
function DatabaseSelector({
  connections,
  selectedConnectionId,
  onSelect
}) {
  // Normalize value to string, handle undefined/null cases
  let value = '';
  if (selectedConnectionId !== undefined && selectedConnectionId !== null && selectedConnectionId !== 'undefined') {
    value = String(selectedConnectionId);
  }
  if (value === 'undefined') value = '';

  // Format options for SelectControl
  const options = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select a connection...', 'gregius-data'),
    value: '',
    disabled: false
  }, ...connections.map(conn => {
    // Use connection name (not ID) for API calls
    const optionValue = conn.name ? String(conn.name) : '';

    // Build label with status indicators
    const statusParts = [conn.name];
    if (conn.is_default) statusParts.push((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(Default)', 'gregius-data'));
    if (conn.is_active === false) statusParts.push((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(Inactive)', 'gregius-data'));
    return {
      label: statusParts.join(' '),
      value: optionValue,
      disabled: conn.is_active === false
    };
  })];

  // Handle selection change
  const handleChange = newValue => {
    // Normalize value before passing to parent
    let v = newValue;
    if (v === undefined || v === null || v === 'undefined') v = '';
    onSelect(v);
  };
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection', 'gregius-data'),
    value,
    options,
    onChange: handleChange,
    __next40pxDefaultSize: true,
    __nextHasNoMarginBottom: true
  });
}
DatabaseSelector.propTypes = {
  connections: (prop_types__WEBPACK_IMPORTED_MODULE_3___default().array).isRequired,
  selectedConnectionId: prop_types__WEBPACK_IMPORTED_MODULE_3___default().oneOfType([(prop_types__WEBPACK_IMPORTED_MODULE_3___default().string), (prop_types__WEBPACK_IMPORTED_MODULE_3___default().number)]),
  onSelect: (prop_types__WEBPACK_IMPORTED_MODULE_3___default().func).isRequired
};

/***/ },

/***/ "./src/scripts/dashboard/components/GregiusLogo.js"
/*!*********************************************************!*\
  !*** ./src/scripts/dashboard/components/GregiusLogo.js ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__);

const GregiusLogo = ({
  className = 'gg-data-logo-clean'
}) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("svg", {
  className: className,
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 300 300",
  width: "28",
  height: "28",
  "aria-hidden": "true",
  focusable: "false",
  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("defs", {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("linearGradient", {
      id: "ggDataCleanLogoGradient",
      x1: "28",
      y1: "28",
      x2: "272",
      y2: "272",
      gradientUnits: "userSpaceOnUse",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("stop", {
        offset: "0%",
        stopColor: "#FF0000",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("animate", {
          attributeName: "stop-color",
          values: "#FF0000;#FF7F00;#FFFF00;#FF0000",
          dur: "16s",
          repeatCount: "indefinite"
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("stop", {
        offset: "45%",
        stopColor: "#00FF00",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("animate", {
          attributeName: "stop-color",
          values: "#00FF00;#0000FF;#4B0082;#00FF00",
          dur: "16s",
          begin: "-3s",
          repeatCount: "indefinite"
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("stop", {
        offset: "72%",
        stopColor: "#8B00FF",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("animate", {
          attributeName: "stop-color",
          values: "#8B00FF;#FF0000;#FF7F00;#8B00FF",
          dur: "16s",
          begin: "-6s",
          repeatCount: "indefinite"
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("stop", {
        offset: "100%",
        stopColor: "#FFFF00",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("animate", {
          attributeName: "stop-color",
          values: "#FFFF00;#00FF00;#0000FF;#FFFF00",
          dur: "16s",
          begin: "-1.5s",
          repeatCount: "indefinite"
        })
      })]
    })
  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("g", {
    fill: "url(#ggDataCleanLogoGradient)",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("g", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
        d: "M150.709,172.563c-5.833,0-11.665-2.227-16.115-6.674c-8.901-8.9-8.901-23.332,0-32.231l89.18-89.184c8.902-8.9,23.33-8.902,32.231,0c8.9,8.899,8.9,23.33,0,32.23l-89.181,89.185C162.375,170.337,156.544,172.563,150.709,172.563z"
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("g", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
        d: "M150.846,298.976c-18.939,0-37.918-3.449-55.643-10.52c-59.397-23.688-81.321-75.575-86.626-90.913c-13.582-39.274-6.68-81.34,3.358-105.431C34.368,38.27,85.823,4.229,149.576,1.052c12.563-0.617,23.271,9.058,23.896,21.628c0.625,12.57-9.059,23.269-21.63,23.896c-45.757,2.279-82.33,25.856-97.833,63.065c-7.266,17.434-11.101,47.717-2.357,73.003c3.706,10.713,19.016,46.952,60.437,63.474c33.125,13.213,74.083,7.93,101.912-13.139c25.052-18.968,39.418-49.295,39.418-83.207c0-12.587,10.205-22.791,22.791-22.791c12.585,0,22.79,10.203,22.79,22.791c0,48.314-20.951,91.887-57.485,119.546C215.778,288.805,183.371,298.976,150.846,298.976z"
      })
    })]
  })]
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (GregiusLogo);

/***/ },

/***/ "./src/scripts/dashboard/components/connections/ConnectionForm.js"
/*!************************************************************************!*\
  !*** ./src/scripts/dashboard/components/connections/ConnectionForm.js ***!
  \************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Connection Form Component
 * 
 * Form for creating and editing PostgreSQL database connections.
 * Includes validation, field types, and security considerations.
 */





// Create fallback NumberControl if not available

const SafeNumberControl = _wordpress_components__WEBPACK_IMPORTED_MODULE_2__.NumberControl || (({
  label,
  value,
  onChange,
  min,
  max,
  help,
  ...props
}) => {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: label,
    value: value?.toString() || '',
    onChange: newValue => {
      const numValue = parseInt(newValue, 10);
      if (!isNaN(numValue)) {
        onChange(numValue);
      }
    },
    type: "number",
    min: min,
    max: max,
    help: help,
    __next40pxDefaultSize: true,
    __nextHasNoMarginBottom: true,
    ...props
  });
});
const ConnectionForm = ({
  initialData = {},
  onSubmit,
  onCancel,
  submitLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Save Connection', 'gregius-data'),
  isEdit = false
}) => {
  // Form state
  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    name: '',
    type: 'postgresql',
    host: 'localhost',
    port: 5432,
    database: '',
    username: '',
    password: '',
    ssl_mode: 'prefer',
    connect_timeout: 30,
    description: '',
    is_active: true,
    publishable_key: '',
    secret_key: '',
    ...initialData,
    // In edit mode, clear masked password to prevent sending it back
    ...(isEdit && initialData.password === '***' ? {
      password: ''
    } : {})
  });
  const [errors, setErrors] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [isSubmitting, setIsSubmitting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  // Database type options (Phase R.2.4)
  const databaseTypeOptions = [{
    value: 'postgresql',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('PostgreSQL (Direct)', 'gregius-data')
  }, {
    value: 'postgrest',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Supabase (REST)', 'gregius-data')
  }];

  // SSL mode options
  const sslModeOptions = [{
    value: 'disable',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Disable - No SSL', 'gregius-data')
  }, {
    value: 'allow',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Allow - SSL if available', 'gregius-data')
  }, {
    value: 'prefer',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prefer - SSL preferred (default)', 'gregius-data')
  }, {
    value: 'require',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Require - SSL required', 'gregius-data')
  }, {
    value: 'verify-ca',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verify CA - Verify certificate authority', 'gregius-data')
  }, {
    value: 'verify-full',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verify Full - Full certificate verification', 'gregius-data')
  }];

  // Update form data when initialData changes
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (initialData && Object.keys(initialData).length > 0) {
      const normalizedInitialData = {
        ...initialData,
        publishable_key: initialData.publishable_key || initialData.api_key || '',
        secret_key: initialData.secret_key || initialData.service_role_key || ''
      };
      setFormData(prev => ({
        ...prev,
        ...normalizedInitialData,
        // In edit mode, clear masked password to prevent sending it back
        ...(isEdit && initialData.password === '***' ? {
          password: ''
        } : {})
      }));
    }
  }, [initialData, isEdit]);

  // Validation function
  const validateForm = () => {
    const newErrors = {};

    // Name validation
    if (!formData.name || formData.name.trim() === '') {
      newErrors.name = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection name is required', 'gregius-data');
    } else if (!/^[a-zA-Z0-9_-]+$/.test(formData.name)) {
      newErrors.name = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection name can only contain letters, numbers, hyphens, and underscores', 'gregius-data');
    }

    // Type-specific validation
    if (formData.type === 'postgrest') {
      // Supabase (PostgREST) validation
      if (!formData.project_url || formData.project_url.trim() === '') {
        newErrors.project_url = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Project URL is required', 'gregius-data');
      } else if (!/^https?:\/\/[^\s]+$/i.test(formData.project_url.trim())) {
        newErrors.project_url = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Invalid URL. Enter a valid http(s):// URL.', 'gregius-data');
      }
      if (!formData.publishable_key || formData.publishable_key.trim() === '') {
        newErrors.publishable_key = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Publishable Key is required', 'gregius-data');
      }
      if (!formData.secret_key || formData.secret_key.trim() === '') {
        newErrors.secret_key = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Secret Key is required', 'gregius-data');
      }
    } else {
      // Direct PostgreSQL validation
      if (!formData.host || formData.host.trim() === '') {
        newErrors.host = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Host is required', 'gregius-data');
      }
      if (!formData.port || formData.port < 1 || formData.port > 65535) {
        newErrors.port = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Port must be between 1 and 65535', 'gregius-data');
      }
      if (!formData.database || formData.database.trim() === '') {
        newErrors.database = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Database name is required', 'gregius-data');
      }
      if (!formData.username || formData.username.trim() === '') {
        newErrors.username = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Username is required', 'gregius-data');
      }

      // Password validation (only for new connections)
      if (!isEdit && (!formData.password || formData.password.trim() === '')) {
        newErrors.password = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Password is required', 'gregius-data');
      }
    }

    // Timeout validation (applies to all types)
    if (!formData.connect_timeout || formData.connect_timeout < 1 || formData.connect_timeout > 300) {
      newErrors.connect_timeout = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Timeout must be between 1 and 300 seconds', 'gregius-data');
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // Handle form submission
  const handleSubmit = async event => {
    event.preventDefault();
    if (!validateForm()) {
      return;
    }
    setIsSubmitting(true);
    try {
      // Prepare submission data - only include fields relevant to provider type
      const submitData = {
        name: formData.name,
        description: formData.description,
        type: formData.type,
        connect_timeout: formData.connect_timeout,
        is_active: formData.is_active
      };

      // Add provider-specific fields
      if (formData.type === 'postgrest') {
        submitData.project_url = formData.project_url;
        // Only include API keys if they were changed (not masked)
        if (formData.publishable_key && formData.publishable_key !== '***') {
          submitData.publishable_key = formData.publishable_key;
        }
        if (formData.secret_key && formData.secret_key !== '***') {
          submitData.secret_key = formData.secret_key;
        }
      } else {
        // PostgreSQL fields
        submitData.host = formData.host;
        submitData.port = formData.port;
        submitData.database = formData.database;
        submitData.username = formData.username;
        submitData.ssl_mode = formData.ssl_mode;

        // Only include password if it was changed (not empty in edit mode)
        if (!isEdit || formData.password && formData.password.trim() !== '') {
          submitData.password = formData.password;
        }
      }
      await onSubmit(submitData);
    } catch (error) {
      setErrors({
        submit: error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('An error occurred while saving', 'gregius-data')
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  // Handle field changes
  const handleFieldChange = (field, value) => {
    // If changing database type, clear provider-specific fields
    if (field === 'type') {
      const newData = {
        name: formData.name,
        description: formData.description,
        type: value,
        connect_timeout: formData.connect_timeout || 30,
        is_active: formData.is_active
      };

      // Add default fields based on new type
      if (value === 'postgrest') {
        newData.project_url = '';
        newData.publishable_key = '';
        newData.secret_key = '';
      } else {
        newData.host = 'localhost';
        newData.port = 5432;
        newData.database = '';
        newData.username = '';
        newData.password = '';
        newData.ssl_mode = 'prefer';
      }
      setFormData(newData);
      setErrors({}); // Clear all errors
      return;
    }
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));

    // Clear field error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({
        ...prev,
        [field]: undefined
      }));
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("form", {
    onSubmit: handleSubmit,
    className: "gg-data-connection-form",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "gg-data-form-fields",
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: '16px'
      },
      children: [errors.submit && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "error",
        isDismissible: false,
        children: errors.submit
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection Name', 'gregius-data'),
        value: formData.name,
        onChange: value => handleFieldChange('name', value),
        error: errors.name,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Unique identifier for this connection (letters, numbers, hyphens, underscores only)', 'gregius-data'),
        disabled: isEdit,
        __next40pxDefaultSize: true,
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Database Type', 'gregius-data'),
        value: formData.type,
        onChange: value => handleFieldChange('type', value),
        options: databaseTypeOptions,
        help: isEdit ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('⚠️ Changing provider type will require re-entering all connection details.', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select the database provider. Supabase works on any WordPress hosting without PHP extensions.', 'gregius-data'),
        __next40pxDefaultSize: true,
        __nextHasNoMarginBottom: true
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Description (Optional)', 'gregius-data'),
        value: formData.description,
        onChange: value => handleFieldChange('description', value),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Brief description of this connection', 'gregius-data'),
        rows: 2,
        __nextHasNoMarginBottom: true
      }), formData.type === 'postgrest' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("fieldset", {
        style: {
          border: '1px solid #ddd',
          padding: '16px',
          borderRadius: '4px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("legend", {
          style: {
            fontWeight: 'bold',
            padding: '0 8px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('PostgREST Project Details', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '12px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Project URL', 'gregius-data'),
            value: formData.project_url || '',
            onChange: value => handleFieldChange('project_url', value),
            error: errors.project_url,
            placeholder: "https://host:port",
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Your PostgREST endpoint URL (e.g. https://your-project.supabase.co or http://host:54421)', 'gregius-data'),
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          })
        })]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("fieldset", {
        style: {
          border: '1px solid #ddd',
          padding: '16px',
          borderRadius: '4px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("legend", {
          style: {
            fontWeight: 'bold',
            padding: '0 8px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection Details', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '12px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            style: {
              display: 'flex',
              gap: '12px',
              alignItems: 'end'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
              style: {
                flex: '1'
              },
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Host', 'gregius-data'),
                value: formData.host,
                onChange: value => handleFieldChange('host', value),
                error: errors.host,
                placeholder: "localhost",
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
              style: {
                width: '120px'
              },
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SafeNumberControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Port', 'gregius-data'),
                value: formData.port,
                onChange: value => handleFieldChange('port', parseInt(value) || 5432),
                error: errors.port,
                min: 1,
                max: 65535
              })
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Database Name', 'gregius-data'),
            value: formData.database,
            onChange: value => handleFieldChange('database', value),
            error: errors.database,
            placeholder: "my_database",
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("fieldset", {
        style: {
          border: '1px solid #ddd',
          padding: '16px',
          borderRadius: '4px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("legend", {
          style: {
            fontWeight: 'bold',
            padding: '0 8px'
          },
          children: formData.type === 'postgrest' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('API Keys', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Authentication', 'gregius-data')
        }), formData.type === 'postgrest' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '12px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Publishable Key', 'gregius-data'),
            type: "password",
            value: formData.publishable_key || '',
            onChange: value => handleFieldChange('publishable_key', value),
            error: errors.publishable_key,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Your publishable key (Settings → API Keys → Publishable and secret API keys)', 'gregius-data'),
            placeholder: "sb_publishable_...",
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Secret Key', 'gregius-data'),
            type: "password",
            value: formData.secret_key || '',
            onChange: value => handleFieldChange('secret_key', value),
            error: errors.secret_key,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Your secret key (Settings → API Keys → Publishable and secret API keys). Keep this secure!', 'gregius-data'),
            placeholder: "sb_secret_...",
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          })]
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '12px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Username', 'gregius-data'),
            value: formData.username,
            onChange: value => handleFieldChange('username', value),
            error: errors.username,
            placeholder: "database_user",
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Password', 'gregius-data'),
            type: "password",
            value: formData.password,
            onChange: value => handleFieldChange('password', value),
            error: errors.password,
            help: isEdit ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Leave blank to keep current password', 'gregius-data') : undefined,
            placeholder: isEdit ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(current password)', 'gregius-data') : '',
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("fieldset", {
        style: {
          border: '1px solid #ddd',
          padding: '16px',
          borderRadius: '4px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("legend", {
          style: {
            fontWeight: 'bold',
            padding: '0 8px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Advanced Settings', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '12px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('SSL Mode', 'gregius-data'),
            value: formData.ssl_mode,
            onChange: value => handleFieldChange('ssl_mode', value),
            options: sslModeOptions,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('SSL encryption settings for the connection', 'gregius-data'),
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SafeNumberControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection Timeout (seconds)', 'gregius-data'),
            value: formData.connect_timeout,
            onChange: value => handleFieldChange('connect_timeout', parseInt(value) || 30),
            error: errors.connect_timeout,
            min: 1,
            max: 300,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Maximum time to wait for connection', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active Connection', 'gregius-data'),
            checked: formData.is_active,
            onChange: value => handleFieldChange('is_active', value),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Enable or disable this connection', 'gregius-data'),
            __nextHasNoMarginBottom: true
          })]
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      style: {
        marginTop: '24px',
        paddingTop: '16px',
        borderTop: '1px solid #ddd',
        display: 'flex',
        gap: '12px',
        justifyContent: 'flex-start'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "primary",
        type: "submit",
        isBusy: isSubmitting,
        disabled: isSubmitting,
        children: isSubmitting ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Saving...', 'gregius-data') : submitLabel
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "link",
        onClick: onCancel,
        disabled: isSubmitting,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cancel', 'gregius-data')
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectionForm);

/***/ },

/***/ "./src/scripts/dashboard/components/connections/ConnectionList.js"
/*!************************************************************************!*\
  !*** ./src/scripts/dashboard/components/connections/ConnectionList.js ***!
  \************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _utils_format_time__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../utils/format-time */ "./src/scripts/dashboard/utils/format-time.js");
/* harmony import */ var _SchemaSetupModal__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./SchemaSetupModal */ "./src/scripts/dashboard/components/connections/SchemaSetupModal.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * Connection List Component
 * 
 * Displays a list of all database connections with health status,
 * actions for edit/delete/test operations.
 */









const ConnectionList = ({
  connections,
  onEdit,
  onDelete,
  onTest,
  onAdd,
  testingConnection,
  testResults,
  onDismissTestResult
}) => {
  const [schemaStatuses, setSchemaStatuses] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [searchSchemaStatuses, setSearchSchemaStatuses] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [healthStatuses, setHealthStatuses] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [creatingSchema, setCreatingSchema] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [creatingSearchSchema, setCreatingSearchSchema] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [fixingSettings, setFixingSettings] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [settingsNeedFix, setSettingsNeedFix] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [settingsFixed, setSettingsFixed] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [checkingHealth, setCheckingHealth] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [upgradingSchema, setUpgradingSchema] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [verifyingSchema, setVerifyingSchema] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [schemaModalOpen, setSchemaModalOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [schemaModalConnection, setSchemaModalConnection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Fetch schema status for all connections
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (connections) {
      Object.keys(connections).forEach(async name => {
        try {
          const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
            path: `/gg-data/v1/schema/status?connection=${name}`
          });
          setSchemaStatuses(prev => ({
            ...prev,
            [name]: response
          }));
        } catch (err) {}
        try {
          const searchResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
            path: `/gg-data/v1/search/status?connection=${name}`
          });
          setSearchSchemaStatuses(prev => ({
            ...prev,
            [name]: searchResponse
          }));
          // If search schema exists, check settings health
          if (searchResponse?.schema_version) {
            checkSettingsHealth(name);
          }
        } catch (err) {
          // Search schema is optional, silently fail
        }

        // Fetch health status
        try {
          const healthResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
            path: `/gg-data/v1/sync/connection-health?connection=${name}`
          });
          if (healthResponse.success) {
            setHealthStatuses(prev => ({
              ...prev,
              [name]: healthResponse.data
            }));
          }
        } catch (err) {}
      });
    }
  }, [connections]);

  // Handler to create schema
  const handleCreateSchema = async connectionName => {
    const connection = connections[connectionName];

    // For Supabase, open schema setup modal
    if (connection.type === 'postgrest') {
      setSchemaModalConnection(connection);
      setSchemaModalOpen(true);
      return;
    }

    // For PDO connections, create schema directly
    try {
      setCreatingSchema(prev => ({
        ...prev,
        [connectionName]: true
      }));
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/schema/create?connection=${connectionName}`,
        method: 'POST'
      });
      if (response.success) {
        // Refresh schema status
        const schemaResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/gg-data/v1/schema/status?connection=${connectionName}`
        });
        setSchemaStatuses(prev => ({
          ...prev,
          [connectionName]: schemaResponse
        }));
      }
    } catch (err) {} finally {
      setCreatingSchema(prev => ({
        ...prev,
        [connectionName]: false
      }));
    }
  };

  // Handler to create search schema
  const handleCreateSearchSchema = async connectionName => {
    try {
      setCreatingSearchSchema(prev => ({
        ...prev,
        [connectionName]: true
      }));
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/search/schema/create`,
        method: 'POST',
        data: {
          connection: connectionName
        }
      });
      if (response.success) {
        // Refresh search schema status
        const searchResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/gg-data/v1/search/status?connection=${connectionName}`
        });
        setSearchSchemaStatuses(prev => ({
          ...prev,
          [connectionName]: searchResponse
        }));
        // Check settings health for this connection
        checkSettingsHealth(connectionName);
      }
    } catch (err) {} finally {
      setCreatingSearchSchema(prev => ({
        ...prev,
        [connectionName]: false
      }));
    }
  };
  const checkSettingsHealth = async connectionName => {
    if (settingsFixed[connectionName]) {
      return;
    }
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/search/language-status?connection=${connectionName}`
      });
      // Show Update button if: language setting missing OR language mismatches current locale
      setSettingsNeedFix(prev => ({
        ...prev,
        [connectionName]: !response.stored || response.mismatch
      }));
    } catch (err) {
      setSettingsNeedFix(prev => ({
        ...prev,
        [connectionName]: true
      }));
    }
  };
  const handleFixSettings = async connectionName => {
    try {
      setFixingSettings(prev => ({
        ...prev,
        [connectionName]: true
      }));
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/search/fix-settings?connection=${connectionName}`,
        method: 'POST'
      });
      if (response.success) {
        const searchResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/gg-data/v1/search/status?connection=${connectionName}`
        });
        setSearchSchemaStatuses(prev => ({
          ...prev,
          [connectionName]: searchResponse
        }));
        setSettingsNeedFix(prev => ({
          ...prev,
          [connectionName]: false
        }));
        setSettingsFixed(prev => ({
          ...prev,
          [connectionName]: true
        }));
      }
    } catch (err) {} finally {
      setFixingSettings(prev => ({
        ...prev,
        [connectionName]: false
      }));
    }
  };

  // Handler to check health
  const handleCheckHealth = async connectionName => {
    try {
      setCheckingHealth(prev => ({
        ...prev,
        [connectionName]: true
      }));
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/gg-data/v1/sync/connection-health/check',
        method: 'POST'
      });
      if (response.success) {
        setHealthStatuses(prev => ({
          ...prev,
          [connectionName]: response.data
        }));
      }
    } catch (err) {} finally {
      setCheckingHealth(prev => ({
        ...prev,
        [connectionName]: false
      }));
    }
  };

  // Handler to upgrade schema
  const handleUpgradeSchema = async connectionName => {
    try {
      setUpgradingSchema(prev => ({
        ...prev,
        [connectionName]: true
      }));
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/schema/upgrade?connection=${connectionName}`,
        method: 'POST'
      });
      if (response.success) {
        // Refresh schema status to remove update banner
        const schemaResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/gg-data/v1/schema/status?connection=${connectionName}`
        });
        setSchemaStatuses(prev => ({
          ...prev,
          [connectionName]: schemaResponse
        }));
      }
    } catch (err) {} finally {
      setUpgradingSchema(prev => ({
        ...prev,
        [connectionName]: false
      }));
    }
  };

  // Handler for successful schema setup
  const handleSchemaSetupSuccess = async () => {
    if (schemaModalConnection) {
      // Find connection name from connection object
      const connectionName = Object.keys(connections).find(name => connections[name] === schemaModalConnection);
      if (connectionName) {
        // Refresh schema status
        const schemaResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/gg-data/v1/schema/status?connection=${connectionName}`
        });
        setSchemaStatuses(prev => ({
          ...prev,
          [connectionName]: schemaResponse
        }));
      }
    }
  };

  // Helper function to get connection status badge
  const getStatusBadge = connection => {
    const isActive = connection.is_active;
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
      className: `components-badge is-${isActive ? 'success' : 'warning'}`,
      children: isActive ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inactive', 'gregius-data')
    });
  };

  // Helper function to format connection info
  const getConnectionInfo = connection => {
    // Supabase uses project URL instead of host:port/database
    if (connection.type === 'postgrest') {
      return connection.project_url || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Supabase Project', 'gregius-data');
    }

    // PostgreSQL format
    const host = connection.host || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Unknown', 'gregius-data');
    const port = connection.port || 5432;
    const database = connection.database || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Unknown', 'gregius-data');
    return `${host}:${port}/${database}`;
  };

  // Helper function to get SSL mode display
  const getSslModeDisplay = sslMode => {
    const modes = {
      'disable': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Disabled', 'gregius-data'),
      'allow': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Allow', 'gregius-data'),
      'prefer': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prefer', 'gregius-data'),
      'require': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Require', 'gregius-data'),
      'verify-ca': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verify CA', 'gregius-data'),
      'verify-full': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verify Full', 'gregius-data')
    };
    return modes[sslMode] || sslMode || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Unknown', 'gregius-data');
  };

  // Helper function to get database type display (Phase R.2.4)
  const getDatabaseTypeDisplay = type => {
    const types = {
      'postgresql': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('PostgreSQL (Direct)', 'gregius-data'),
      'supabase': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Supabase (HTTP)', 'gregius-data'),
      'mysql': (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('MySQL', 'gregius-data')
    };
    return types[type] || type || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('PostgreSQL', 'gregius-data');
  };

  // If no connections exist
  if (!connections || Object.keys(connections).length === 0) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
      isRounded: false,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
        style: {
          textAlign: 'center',
          padding: '60px 40px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          style: {
            color: '#646970',
            marginBottom: '24px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add your first PostgreSQL database connection to get started', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "secondary",
          onClick: onAdd,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add Your First Connection', 'gregius-data')
        })]
      })
    });
  }

  // Render list of connections
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
    children: [schemaModalConnection && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_SchemaSetupModal__WEBPACK_IMPORTED_MODULE_6__["default"], {
      isOpen: schemaModalOpen,
      onRequestClose: () => {
        setSchemaModalOpen(false);
        setSchemaModalConnection(null);
      },
      connectionName: Object.keys(connections).find(name => connections[name] === schemaModalConnection),
      onSuccess: handleSchemaSetupSuccess
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: '2rem'
      },
      children: Object.entries(connections).map(([name, connection]) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
        isRounded: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            style: {
              display: 'flex',
              flexWrap: 'wrap',
              gap: '1rem',
              justifyContent: 'space-between',
              alignItems: 'flex-start',
              width: '100%'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                style: {
                  display: 'flex',
                  flexDirection: 'row',
                  alignItems: 'center',
                  gap: '.25em'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
                  level: 3,
                  style: {
                    margin: 0
                  },
                  children: name
                }), getStatusBadge(connection)]
              }), connection.description && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
                className: "description",
                style: {
                  margin: 0
                },
                children: connection.description
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DropdownMenu, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection actions', 'gregius-data'),
              controls: [{
                title: testingConnection === name ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Testing...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Test Connection', 'gregius-data'),
                onClick: () => onTest(name),
                isDisabled: testingConnection === name
              }, {
                title: checkingHealth[name] ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Checking...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Check Health', 'gregius-data'),
                onClick: () => handleCheckHealth(name),
                isDisabled: checkingHealth[name]
              }, {
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Edit Connection', 'gregius-data'),
                onClick: () => onEdit({
                  name,
                  ...connection
                })
              }, {
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Delete Connection', 'gregius-data'),
                onClick: () => onDelete(name),
                className: 'has-text-color has-vivid-red-color'
              }]
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
          children: [schemaStatuses[name]?.update_available && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
            status: "warning",
            isDismissible: false,
            style: {
              marginBottom: '16px'
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              style: {
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                gap: '16px'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Schema Update Available', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                  style: {
                    marginLeft: '8px'
                  },
                  children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('New schema version', 'gregius-data'), " ", schemaStatuses[name].plugin_version, " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('is available', 'gregius-data')]
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "primary",
                onClick: () => handleUpgradeSchema(name),
                disabled: upgradingSchema[name],
                style: {
                  flexShrink: 0
                },
                children: upgradingSchema[name] ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Updating...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Update Schema', 'gregius-data')
              })]
            })
          }), testResults && testResults.connectionName === name && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
            status: testResults.result.success ? 'success' : 'error',
            isDismissible: true,
            onDismiss: onDismissTestResult,
            style: {
              marginBottom: '16px'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Test Result: ', 'gregius-data')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
              children: testResults.result.message
            }), testResults.result.success && testResults.result.response_time && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
              style: {
                marginLeft: '8px',
                fontSize: '0.9em',
                opacity: 0.8
              },
              children: ["(", testResults.result.response_time, "ms)"]
            })]
          }), healthStatuses[name] && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
              columns: 3,
              gap: 4,
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Uptime: ', 'gregius-data')
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                  className: "components-badge is-info",
                  children: [Math.round(healthStatuses[name].uptime_percentage || 100), "%"]
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Total Checks: ', 'gregius-data')
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  className: "components-badge is-info",
                  children: healthStatuses[name].total_checks || 0
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Consecutive Failures: ', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  className: `components-badge ${healthStatuses[name].consecutive_failures > 0 ? 'is-warning' : 'is-info'}`,
                  children: healthStatuses[name].consecutive_failures || 0
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Total Failures: ', 'gregius-data')
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  className: "components-badge is-info",
                  children: healthStatuses[name].total_failures || 0
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Last Health Check: ', 'gregius-data')
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  className: "components-badge is-info",
                  children: (0,_utils_format_time__WEBPACK_IMPORTED_MODULE_5__.formatRelativeTime)(healthStatuses[name].last_check)
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Last Successful Check: ', 'gregius-data')
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  className: "components-badge is-info",
                  children: (0,_utils_format_time__WEBPACK_IMPORTED_MODULE_5__.formatRelativeTime)(healthStatuses[name].last_success)
                })]
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("hr", {
              style: {
                margin: '16px 0'
              }
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
            columns: 3,
            gap: 4,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection:', 'gregius-data')
              }), " ", getConnectionInfo(connection)]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Database Type:', 'gregius-data')
              }), " ", getDatabaseTypeDisplay(connection.type)]
            }), connection.type !== 'supabase' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Username:', 'gregius-data')
              }), " ", connection.username || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not set', 'gregius-data')]
            }), connection.type !== 'supabase' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('SSL Mode:', 'gregius-data')
              }), " ", getSslModeDisplay(connection.ssl_mode)]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Timeout:', 'gregius-data')
              }), " ", connection.connect_timeout || 30, "s"]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("hr", {
            style: {
              margin: '16px 0'
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
            columns: 3,
            gap: 4,
            style: {
              marginTop: '16px'
            },
            children: [schemaStatuses[name] && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "gg-health-detail",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Schema: ', 'gregius-data')
              }), schemaStatuses[name].schema_version && schemaStatuses[name].schema_version !== '0.0.0' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                children: schemaStatuses[name].schema_version
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                style: {
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px',
                  flexWrap: 'wrap'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not Created', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                  variant: "primary",
                  onClick: () => handleCreateSchema(name),
                  disabled: creatingSchema[name],
                  children: connection.type === 'postgrest' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Setup Schema', 'gregius-data') : creatingSchema[name] ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Creating...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Create', 'gregius-data')
                })]
              })]
            }), schemaStatuses[name] && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "gg-health-detail",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('pg_trgm: ', 'gregius-data')
              }), schemaStatuses[name].pg_trgm_extension ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installed', 'gregius-data')
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                style: {
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not Installed', 'gregius-data')
                })
              })]
            }), schemaStatuses[name] && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "gg-health-detail",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('pgvector: ', 'gregius-data')
              }), schemaStatuses[name].vector_extension ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installed', 'gregius-data')
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                style: {
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not Installed', 'gregius-data')
                })
              })]
            })]
          })]
        })]
      }, `connection-${name}`))
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectionList);

/***/ },

/***/ "./src/scripts/dashboard/components/connections/SchemaSetupModal.js"
/*!**************************************************************************!*\
  !*** ./src/scripts/dashboard/components/connections/SchemaSetupModal.js ***!
  \**************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/check.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/copy.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Schema Setup Modal Component
 * 
 * Single-step modal for Supabase schema setup:
 * - Display SQL code in scrollable area
 * - Copy to clipboard button
 * - Verify schema button
 * - Auto-close on success
 */







const SchemaSetupModal = ({
  isOpen,
  onRequestClose,
  connectionName,
  onSuccess
}) => {
  const [sqlContent, setSqlContent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [copied, setCopied] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [verifying, setVerifying] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [verifyResult, setVerifyResult] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);

  // Load SQL content when modal opens
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (isOpen && !sqlContent) {
      loadSqlContent();
    }
  }, [isOpen]);
  const loadSqlContent = async () => {
    try {
      setLoading(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default()({
        path: `/gg-data/v1/schema/sql?connection=${connectionName}`
      });
      if (response.success) {
        setSqlContent(response.sql);
      }
    } catch (err) {} finally {
      setLoading(false);
    }
  };
  const handleCopySQL = async () => {
    try {
      if (navigator.clipboard) {
        await navigator.clipboard.writeText(sqlContent);
      } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = sqlContent;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
      }
      setCopied(true);
      setTimeout(() => setCopied(false), 3000);
    } catch (err) {}
  };
  const handleVerifySchema = async () => {
    try {
      setVerifying(true);
      setVerifyResult(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default()({
        path: `/gg-data/v1/schema/verify`,
        method: 'POST',
        data: {
          connection: connectionName
        }
      });
      setVerifyResult(response);
      if (response.success && response.status === 'ready') {
        // Wait a moment to show success message, then close
        setTimeout(() => {
          onSuccess();
          onRequestClose();
        }, 1500);
      }
    } catch (err) {
      setVerifyResult({
        success: false,
        message: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verification failed', 'gregius-data')
      });
    } finally {
      setVerifying(false);
    }
  };
  const handleClose = () => {
    setCopied(false);
    setVerifyResult(null);
    onRequestClose();
  };
  if (!isOpen) return null;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Setup Supabase Schema', 'gregius-data'),
    onRequestClose: handleClose,
    style: {
      maxWidth: '800px'
    },
    className: "gg-schema-setup-modal",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalVStack, {
      spacing: 4,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalText, {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Paste and run the SQL code in the Supabase SQL Editor to create your database schema:', 'gregius-data')
      }), loading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          textAlign: 'center',
          padding: '40px'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {})
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          position: 'relative'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("pre", {
          style: {
            background: '#f5f5f5',
            border: '1px solid #ddd',
            borderRadius: '4px',
            padding: '16px',
            maxHeight: '400px',
            overflow: 'auto',
            fontSize: '12px',
            fontFamily: 'monospace',
            lineHeight: '1.5'
          },
          children: sqlContent
        })
      }), verifyResult && (verifyResult.success ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "success",
        isDismissible: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Schema Verified!', 'gregius-data')
        }), ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Version', 'gregius-data'), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("code", {
          children: verifyResult.version
        }), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('is ready to use.', 'gregius-data')]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "warning",
        isDismissible: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Schema Not Found', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), verifyResult.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please run the SQL in Supabase SQL Editor first.', 'gregius-data')]
      })), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHStack, {
        spacing: 2,
        justify: "flex-start",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: handleCopySQL,
          icon: copied ? _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"] : _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
          disabled: loading || verifying,
          children: copied ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Copied!', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Copy to Clipboard', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "secondary",
          onClick: handleVerifySchema,
          isBusy: verifying,
          disabled: loading || verifying,
          children: verifying ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verifying...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verify Schema', 'gregius-data')
        })]
      })]
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SchemaSetupModal);

/***/ },

/***/ "./src/scripts/dashboard/components/search/SearchHealthCard.js"
/*!*********************************************************************!*\
  !*** ./src/scripts/dashboard/components/search/SearchHealthCard.js ***!
  \*********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _utils_format_time__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../utils/format-time */ "./src/scripts/dashboard/utils/format-time.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Search Health Card Component
 *
 * Displays PostgreSQL search health status with auto-refresh.
 * Loads connection from global search settings.
 *
 * @since 2.0.0
 */








// Global settings connection constant (must match PHP GG_DATA_SEARCH_SETTINGS_CONNECTION)

const SEARCH_SETTINGS_CONNECTION = '__global__';
const SearchHealthCard = () => {
  const [searchConnection, setSearchConnection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [health, setHealth] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [lastUpdate, setLastUpdate] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [healthCheckNotice, setHealthCheckNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null); // { status: 'success'|'error', message: '...' }

  // Load search connection from global settings
  const loadSearchConnection = async () => {
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/connection?connection=${SEARCH_SETTINGS_CONNECTION}`
      });
      if (response && response.value) {
        setSearchConnection(response.value);
      } else {
        setSearchConnection(null);
      }
    } catch (err) {
      // Silently fail - will show empty state
      console.error('Failed to load search connection:', err);
    } finally {
      setLoading(false);
    }
  };

  // Initial load
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    loadSearchConnection();
  }, []);

  // Poll for settings changes every 5 seconds (in case user changes connection in settings card)
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    const interval = setInterval(() => {
      loadSearchConnection();
    }, 5000);
    return () => clearInterval(interval);
  }, []);

  // Fetch health status
  const fetchHealth = async () => {
    if (!searchConnection) return;
    try {
      setLoading(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/health?connection=${searchConnection}`
      });
      if (response.success) {
        setHealth(response.data);
        setError(null);
        setLastUpdate(new Date());
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // Initial load and when connection changes
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (searchConnection) {
      fetchHealth();
    } else {
      setHealth(null);
      setError(null);
    }
  }, [searchConnection]);

  // Auto-refresh every 30 seconds (only when connected)
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (!searchConnection) return;
    const interval = setInterval(() => {
      fetchHealth();
    }, 30000);
    return () => clearInterval(interval);
  }, [searchConnection]);

  // Manual health check
  const runHealthCheck = async () => {
    if (!searchConnection) return;
    setHealthCheckNotice(null);
    const startTime = Date.now();
    try {
      setLoading(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/health/check?connection=${searchConnection}`,
        method: 'POST'
      });
      if (response.success) {
        // Refresh health status after check
        await fetchHealth();
        const duration = Date.now() - startTime;

        // Get health data for message
        const healthData = response.data || health;
        const successRate = healthData?.success_rate || 100;
        const lastLatency = healthData?.last_latency_ms || duration;

        // Determine message based on health status
        let message;
        if (successRate === 100) {
          message = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(`Search health check completed. All systems operational (${lastLatency}ms)`, 'gregius-data');
        } else {
          message = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(`Search health check completed. Success rate: ${successRate}% (${lastLatency}ms)`, 'gregius-data');
        }
        setHealthCheckNotice({
          status: successRate >= 95 ? 'success' : 'warning',
          message: message
        });
      }
    } catch (err) {
      const errorMessage = err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Health check failed', 'gregius-data');
      setError(errorMessage);
      setHealthCheckNotice({
        status: 'error',
        message: errorMessage
      });
      setLoading(false);
    }
  };

  // Reset health metrics
  const resetHealth = async () => {
    if (!searchConnection) return;
    if (!confirm('Reset all search health metrics? This cannot be undone.')) {
      return;
    }
    try {
      setLoading(true);
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/health/reset?connection=${searchConnection}`,
        method: 'POST'
      });

      // Refresh after reset
      fetchHealth();
    } catch (err) {
      setError(err.message);
      setLoading(false);
    }
  };

  // Get status badge color
  const getStatusBadgeClass = status => {
    switch (status) {
      case 'active':
        return 'gg-badge gg-badge-success';
      case 'degraded':
        return 'gg-badge gg-badge-warning';
      case 'critical':
        return 'gg-badge gg-badge-error';
      default:
        return 'gg-badge gg-badge-neutral';
    }
  };

  // Get status label (only show if degraded or critical)
  const getStatusLabel = status => {
    switch (status) {
      case 'degraded':
        return 'Degraded (Some Failures)';
      case 'critical':
        return 'Critical (MySQL Fallback)';
      default:
        return null;
      // Don't show status when everything is working
    }
  };

  // Format relative time
  if (loading && !health) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      isRounded: false,
      className: "gg-search-health-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHeading, {
          level: 3,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Health', 'gregius-data')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "gg-search-health-loading",
          style: {
            display: 'flex',
            alignItems: 'center',
            gap: '8px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            style: {
              margin: 0
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading search health...', 'gregius-data')
          })]
        })
      })]
    });
  }

  // No connection selected - show empty state
  if (!searchConnection || !health) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      isRounded: false,
      className: "gg-search-health-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHeading, {
          level: 3,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Health', 'gregius-data')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalGrid, {
          columns: 3,
          gap: 4,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: "Success Rate:"
            }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "components-badge",
              children: "\u2014"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: "Total Searches:"
            }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "components-badge",
              children: "\u2014"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: "Consecutive Failures:"
            }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "components-badge",
              children: "\u2014"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: "Last Latency:"
            }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "components-badge",
              children: "\u2014"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: "Last Health Check:"
            }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "components-badge",
              children: "\u2014"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: "Last Successful Search:"
            }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "components-badge",
              children: "\u2014"
            })]
          })]
        }), !searchConnection && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            marginTop: '16px',
            color: '#757575'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enable search and select a connection to view health metrics.', 'gregius-data')
        })]
      })]
    });
  }
  if (error && !health) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "gg-search-health-card gg-search-health-error",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
        status: "error",
        isDismissible: false,
        children: error
      })
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
    isRounded: false,
    className: "gg-search-health-card",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          width: '100%'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHeading, {
            level: 3,
            style: {
              margin: 0
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Health', 'gregius-data')
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.DropdownMenu, {
          icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search health actions', 'gregius-data'),
          controls: [{
            title: loading ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Running...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Run Health Check', 'gregius-data'),
            onClick: runHealthCheck,
            isDisabled: loading
          }, {
            title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reset Health', 'gregius-data'),
            onClick: resetHealth,
            isDisabled: loading,
            className: 'has-text-color has-vivid-red-color'
          }]
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
      children: [healthCheckNotice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
        status: healthCheckNotice.status,
        isDismissible: true,
        onRemove: () => setHealthCheckNotice(null),
        children: healthCheckNotice.message
      }), health.status !== 'active' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        className: "gg-health-status",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
          className: getStatusBadgeClass(health.status),
          children: getStatusLabel(health.status)
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalGrid, {
        columns: 3,
        gap: 4,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: "Success Rate:"
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
            className: "components-badge is-info",
            children: [health.success_rate, "%"]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: "Total Searches:"
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "components-badge is-info",
            children: health.total_searches
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: "Consecutive Failures:"
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: `components-badge ${health.consecutive_failures > 0 ? 'is-warning' : 'is-info'}`,
            children: health.consecutive_failures
          })]
        }), health.last_latency_ms !== null && health.last_latency_ms !== undefined && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: "Last Latency:"
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
            className: "components-badge is-info",
            children: [health.last_latency_ms, "ms"]
          })]
        }), lastUpdate && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: "Last Health Check:"
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "components-badge is-info",
            children: (0,_utils_format_time__WEBPACK_IMPORTED_MODULE_5__.formatRelativeTime)(lastUpdate.toISOString())
          })]
        }), health.last_success && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: "Last Successful Search:"
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "components-badge is-info",
            children: (0,_utils_format_time__WEBPACK_IMPORTED_MODULE_5__.formatRelativeTime)(health.last_success)
          })]
        })]
      }), health.last_error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "gg-health-detail gg-error",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
          children: "Last Error: "
        }), health.last_error, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("small", {
          children: (0,_utils_format_time__WEBPACK_IMPORTED_MODULE_5__.formatRelativeTime)(health.last_error_time)
        })]
      }), health.recent_errors && health.recent_errors.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("details", {
        className: "gg-recent-errors",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("summary", {
          children: ["Recent Errors (", health.recent_errors.length, ")"]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("ul", {
          children: health.recent_errors.map((error, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("li", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("strong", {
              children: [error.timestamp, ": "]
            }), error.message]
          }, index))
        })]
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SearchHealthCard);

/***/ },

/***/ "./src/scripts/dashboard/components/search/SearchSettingsCard.js"
/*!***********************************************************************!*\
  !*** ./src/scripts/dashboard/components/search/SearchSettingsCard.js ***!
  \***********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Search Settings Card Component
 *
 * Enables/disables PostgreSQL full-text search with field weighting.
 * Search settings are GLOBAL (stored under '__global__' scope)
 * because search is a site-wide feature, not per-database-connection.
 *
 * @since 2.0.0
 */






// Global connection name for search settings (matches PHP constant)

const SEARCH_SETTINGS_CONNECTION = '__global__';
const SearchSettingsCard = ({
  connections = []
}) => {
  const [searchEnabled, setSearchEnabled] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [searchConnection, setSearchConnection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)('');
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [success, setSuccess] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [schemaVersion, setSchemaVersion] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);

  // Phase 2.0.3: Language status state
  const [languageStatus, setLanguageStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [isCheckingLanguage, setIsCheckingLanguage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [isUpdatingLanguage, setIsUpdatingLanguage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);

  // Phase 5-6: Typo tolerance state
  const [typoTolerance, setTypoTolerance] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [similarityThreshold, setSimilarityThreshold] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(0.3);
  const [retrievalMode, setRetrievalMode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)('hybrid_default');
  const [telemetryExpensiveProbesEnabled, setTelemetryExpensiveProbesEnabled] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [extensionStatus, setExtensionStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [loadingTypoSettings, setLoadingTypoSettings] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [savingTypoSettings, setSavingTypoSettings] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);

  // Embedding model state
  const [embeddingModel, setEmbeddingModel] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)('hashingtf-murmur3-1024');
  const [availableModels, setAvailableModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)([]);
  const [loadingModels, setLoadingModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [savingEmbeddingModel, setSavingEmbeddingModel] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);

  // Load global search settings on mount
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    loadGlobalSearchSettings();
  }, []);

  // Load connection-specific data when searchConnection changes
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (searchConnection) {
      checkSchemaVersion();
      checkLanguageStatus();
      checkExtensionStatus();
      loadTypoToleranceSettings();
      loadAvailableModels();
    } else {
      setSchemaVersion(null);
      setLanguageStatus(null);
      setExtensionStatus(null);
      setAvailableModels([]);
    }
  }, [searchConnection]);

  // Load all global search settings
  const loadGlobalSearchSettings = async () => {
    try {
      setLoading(true);
      setError(null);

      // Load global search settings in parallel.
      const [enabledRes, connectionRes, modelRes, retrievalModeRes, observabilityRes] = await Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/enabled?connection=${SEARCH_SETTINGS_CONNECTION}`
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/connection?connection=${SEARCH_SETTINGS_CONNECTION}`
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/embedding_model?connection=${SEARCH_SETTINGS_CONNECTION}`
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/retrieval_mode?connection=${SEARCH_SETTINGS_CONNECTION}`
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/observability_enabled?connection=${SEARCH_SETTINGS_CONNECTION}`
      })]);
      if (enabledRes) {
        setSearchEnabled(enabledRes.value === '1' || enabledRes.value === true || enabledRes.value === 1);
      }
      if (connectionRes && connectionRes.value) {
        setSearchConnection(connectionRes.value);
      }
      if (modelRes && modelRes.value) {
        setEmbeddingModel(modelRes.value);
      }
      if (retrievalModeRes && retrievalModeRes.value) {
        setRetrievalMode(retrievalModeRes.value);
      }
      if (observabilityRes) {
        const observabilityEnabled = observabilityRes.value === '1' || observabilityRes.value === true || observabilityRes.value === 1;
        setTelemetryExpensiveProbesEnabled(observabilityEnabled);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // Handle expensive telemetry probe toggle (global setting)
  const handleTelemetryToggle = async newValue => {
    try {
      setSaving(true);
      setError(null);
      setSuccess(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/observability_enabled`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          value: newValue
        }
      });
      if (response && response.updated) {
        setTelemetryExpensiveProbesEnabled(newValue);
        setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Observability settings updated successfully!', 'gregius-data'));
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update observability setting: ', 'gregius-data') + err.message);
      setTelemetryExpensiveProbesEnabled(!newValue);
    } finally {
      setSaving(false);
    }
  };
  const checkSchemaVersion = async () => {
    if (!searchConnection) {
      setSchemaVersion(null);
      return;
    }
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/status?connection=${searchConnection}`
      });
      if (response && response.success) {
        // Check both schema_version setting and function_exists
        if (response.schema_version || response.data && response.data.function_exists) {
          setSchemaVersion(response.schema_version || '1.0.0');
        } else {
          setSchemaVersion(null);
        }
      }
    } catch (err) {
      // Schema doesn't exist yet, that's okay
      setSchemaVersion(null);
    }
  };

  // Phase 2.0.3: Check language status
  const checkLanguageStatus = async () => {
    if (!searchConnection) {
      setLanguageStatus(null);
      return;
    }
    try {
      setIsCheckingLanguage(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/language-status?connection=${searchConnection}`
      });
      if (response && response.success) {
        setLanguageStatus(response);
      }
    } catch (err) {
      // Language status not available (schema not created yet), that's okay
      setLanguageStatus(null);
    } finally {
      setIsCheckingLanguage(false);
    }
  };

  // Phase 2.0.3: Update search language
  const handleUpdateLanguage = async () => {
    try {
      setIsUpdatingLanguage(true);
      setError(null);
      setSuccess(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/update-language`,
        method: 'POST',
        data: {
          connection: searchConnection
        }
      });
      if (response && response.success) {
        setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search language updated successfully!', 'gregius-data'));
        // Refresh language status
        await checkLanguageStatus();
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update search language: ', 'gregius-data') + err.message);
    } finally {
      setIsUpdatingLanguage(false);
    }
  };

  // Phase 5-6: Check extension status
  const checkExtensionStatus = async () => {
    if (!searchConnection) {
      setExtensionStatus(null);
      return;
    }
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/typo-tolerance-status?connection=${searchConnection}`
      });
      if (response && response.success) {
        setExtensionStatus(response);
      } else {
        setExtensionStatus(null);
      }
    } catch (err) {
      setExtensionStatus(null);
    }
  };

  // Phase 5-6: Load typo tolerance settings (global)
  const loadTypoToleranceSettings = async () => {
    try {
      setLoadingTypoSettings(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/typo-tolerance?connection=${SEARCH_SETTINGS_CONNECTION}`
      });
      if (response && response.success) {
        setTypoTolerance(response.typo_tolerance);
        setSimilarityThreshold(response.similarity_threshold);
        if (response.retrieval_mode) {
          setRetrievalMode(response.retrieval_mode);
        }
      }
    } catch (err) {
      // Use defaults on error
    } finally {
      setLoadingTypoSettings(false);
    }
  };

  // Handle retrieval mode toggle (global setting).
  const handleRetrievalModeToggle = async isMergeEnabled => {
    const newMode = isMergeEnabled ? 'hybrid_default' : 'postgresql_only';
    try {
      setSavingTypoSettings(true);
      setError(null);
      setSuccess(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/typo-tolerance`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          retrieval_mode: newMode
        }
      });
      if (response && response.success) {
        setRetrievalMode(newMode);
        setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search retrieval mode updated!', 'gregius-data'));
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update retrieval mode: ', 'gregius-data') + err.message);
    } finally {
      setSavingTypoSettings(false);
    }
  };

  // Phase 5-6: Handle typo tolerance toggle (global setting)
  const handleTypoToleranceToggle = async newValue => {
    try {
      setSavingTypoSettings(true);
      setError(null);
      setSuccess(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/typo-tolerance`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          typo_tolerance: newValue
        }
      });
      if (response && response.success) {
        setTypoTolerance(newValue);
        setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Typo tolerance settings updated!', 'gregius-data'));
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update typo tolerance: ', 'gregius-data') + err.message);
      setTypoTolerance(!newValue);
    } finally {
      setSavingTypoSettings(false);
    }
  };

  // Phase 5-6: Handle similarity threshold change (global setting)
  const handleSimilarityThresholdChange = async newValue => {
    try {
      setSavingTypoSettings(true);
      setError(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/search/typo-tolerance`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          similarity_threshold: parseFloat(newValue)
        }
      });
      if (response && response.success) {
        setSimilarityThreshold(parseFloat(newValue));
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update similarity threshold: ', 'gregius-data') + err.message);
    } finally {
      setSavingTypoSettings(false);
    }
  };

  // Load available embedding models from the selected search connection
  const loadAvailableModels = async () => {
    if (!searchConnection) {
      setAvailableModels([{
        model_key: 'hashingtf-murmur3-1024',
        model_name: 'Hashing TF Murmur3 1024D'
      }]);
      return;
    }
    try {
      setLoadingModels(true);
      // Get active_models from vectors category for the selected connection
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/vectors/active_models?connection=${searchConnection}`
      });
      if (response && response.value && Array.isArray(response.value)) {
        // Transform model keys into option format
        const models = response.value.map(modelKey => {
          // Convert model key to display name
          const displayName = modelKey.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ').replace(/(\d+)D?$/, '$1D');
          return {
            model_key: modelKey,
            model_name: displayName
          };
        });
        setAvailableModels(models);
      } else {
        // Use default if no models configured
        setAvailableModels([{
          model_key: 'hashingtf-murmur3-1024',
          model_name: 'Hashing TF Murmur3 1024D'
        }]);
      }
    } catch (err) {
      // Use default if loading fails
      setAvailableModels([{
        model_key: 'hashingtf-murmur3-1024',
        model_name: 'Hashing TF Murmur3 1024D'
      }]);
    } finally {
      setLoadingModels(false);
    }
  };

  // Handle embedding model change (global setting)
  const handleEmbeddingModelChange = async newModel => {
    try {
      setSavingEmbeddingModel(true);
      setError(null);
      setSuccess(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/embedding_model`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          value: newModel
        }
      });
      if (response && response.updated) {
        setEmbeddingModel(newModel);
        setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Embedding model updated successfully!', 'gregius-data'));
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update embedding model: ', 'gregius-data') + err.message);
    } finally {
      setSavingEmbeddingModel(false);
    }
  };

  // Handle search connection change
  const handleConnectionChange = async newConnection => {
    try {
      setSaving(true);
      setError(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/connection`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          value: newConnection
        }
      });
      if (response && response.updated) {
        setSearchConnection(newConnection);
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update search connection: ', 'gregius-data') + err.message);
    } finally {
      setSaving(false);
    }
  };

  // Handle search toggle (global setting)
  const handleToggle = async newValue => {
    try {
      setSaving(true);
      setError(null);
      setSuccess(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/settings/search/enabled`,
        method: 'POST',
        data: {
          connection: SEARCH_SETTINGS_CONNECTION,
          value: newValue
        }
      });
      if (response && response.updated) {
        setSearchEnabled(newValue);
        setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search settings updated successfully!', 'gregius-data'));

        // Also save the current embedding model and connection when enabling search
        if (newValue) {
          if (embeddingModel) {
            await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
              path: `/gg-data/v1/settings/search/embedding_model`,
              method: 'POST',
              data: {
                connection: SEARCH_SETTINGS_CONNECTION,
                value: embeddingModel
              }
            });
          }
          if (searchConnection) {
            await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
              path: `/gg-data/v1/settings/search/connection`,
              method: 'POST',
              data: {
                connection: SEARCH_SETTINGS_CONNECTION,
                value: searchConnection
              }
            });
          }
        }
      }
    } catch (err) {
      setError(err.message);
      setSearchEnabled(!newValue);
    } finally {
      setSaving(false);
    }
  };

  // Loading state
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      isRounded: false,
      className: "gg-search-settings-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHeading, {
          level: 3,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Settings', 'gregius-data')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          className: "gg-search-settings-loading",
          style: {
            display: 'flex',
            alignItems: 'center',
            gap: '8px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            style: {
              margin: 0
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading search settings...', 'gregius-data')
          })]
        })
      })]
    });
  }

  // Format connection options for SelectControl
  const connectionOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select a connection...', 'gregius-data'),
    value: ''
  }, ...connections.map(conn => ({
    label: conn.is_default ? `${conn.name} (Default)` : conn.name,
    value: conn.name || '',
    disabled: conn.is_active === false
  }))];
  const retrievalMergeEnabled = retrievalMode !== 'postgresql_only';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
    isRounded: false,
    className: "gg-search-settings-card",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHeading, {
        level: 3,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Settings', 'gregius-data')
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
      children: [error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
        status: "error",
        isDismissible: true,
        onRemove: () => setError(null),
        children: error
      }), success && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
        status: "success",
        isDismissible: true,
        onRemove: () => setSuccess(null),
        children: success
      }), languageStatus && languageStatus.mismatch && schemaVersion && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
          status: "warning",
          isDismissible: false,
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: "flex",
              flexDirection: "column",
              gap: "12px"
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search language mismatch detected', 'gregius-data')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Your WordPress locale has changed. The search function is using ', 'gregius-data'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
                children: languageStatus.stored
              }), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(' but your site is now set to ', 'gregius-data'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
                children: languageStatus.current
              }), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(' (', 'gregius-data'), languageStatus.locale, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(').', 'gregius-data')]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
              variant: "secondary",
              onClick: handleUpdateLanguage,
              isBusy: isUpdatingLanguage,
              disabled: isUpdatingLanguage,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Update Search Language', 'gregius-data')
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            marginTop: '20px'
          }
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "gg-search-settings-content",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enable Enhanced Search', 'gregius-data'),
          help: searchEnabled ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enhanced search is active site-wide with full-text search, field weighting (title matches rank higher), stemming ("running" finds "run", "runs", "ran"), stop word filtering, typo tolerance (if pg_trgm extension available), and vector-based semantic search (finds related content "vehicle" also finds posts about "car" and "automobile").', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enable enhanced search site-wide with full-text search, typo tolerance, and vector-based semantic search to find related content by meaning.', 'gregius-data'),
          checked: searchEnabled,
          onChange: handleToggle,
          disabled: loading || saving,
          __nextHasNoMarginBottom: true
        }), searchEnabled && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              marginTop: '16px'
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enhance Observability', 'gregius-data'),
            help: telemetryExpensiveProbesEnabled ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Detailed observability values are enabled. Calculations can increase response latency.', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Detailed observability values are disabled by default to keep response latency low.', 'gregius-data'),
            checked: telemetryExpensiveProbesEnabled,
            onChange: handleTelemetryToggle,
            disabled: loading || saving,
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              marginTop: '16px'
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Dual Retrieval', 'gregius-data'),
            help: retrievalMergeEnabled ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Running PostgreSQL semantic vector search merged with WordPress MySQL. Ensures full coverage for unsynced post types, increases search latency.', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Running pure PostgreSQL semantic vector search. Provides ultra-fast, high-accuracy results exclusively for synced content only.', 'gregius-data'),
            checked: retrievalMergeEnabled,
            onChange: handleRetrievalModeToggle,
            disabled: loadingTypoSettings || savingTypoSettings,
            __nextHasNoMarginBottom: true
          })]
        }), searchEnabled && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              marginTop: '20px'
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Connection', 'gregius-data'),
            value: searchConnection,
            options: connectionOptions,
            onChange: handleConnectionChange,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select the database connection for search queries.', 'gregius-data'),
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              marginTop: '20px'
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Embedding Model', 'gregius-data'),
            value: embeddingModel,
            options: [{
              label: loadingModels ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading models...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select a model', 'gregius-data'),
              value: '',
              disabled: true
            }, ...availableModels.map(model => ({
              label: model.model_name || model.model_key,
              value: model.model_key
            }))],
            onChange: handleEmbeddingModelChange,
            disabled: loadingModels || savingEmbeddingModel,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select which embedding model to use for semantic search. Must match the vector embeddings in your database.', 'gregius-data'),
            __nextHasNoMarginBottom: true
          })]
        }), searchEnabled && extensionStatus && !extensionStatus.is_installed && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              marginTop: '16px'
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
            status: "info",
            isDismissible: false,
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              style: {
                display: "flex",
                flexDirection: "column",
                gap: "12px"
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('pg_trgm extension not installed', 'gregius-data')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enhanced search is active but typo tolerance is unavailable. The pg_trgm extension is required for typo forgiveness features. Contact your hosting provider or database administrator to install it.', 'gregius-data')
              })]
            })
          })]
        })]
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SearchSettingsCard);

/***/ },

/***/ "./src/scripts/dashboard/components/sync/ContentSyncTable.js"
/*!*******************************************************************!*\
  !*** ./src/scripts/dashboard/components/sync/ContentSyncTable.js ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Content Sync Table Component
 * 
 * Unified interface for managing content synchronization.
 * Combines configuration (toggles) and validation (counts/drift) into a single view.
 */







const ContentSyncTable = ({
  selectedConnectionId,
  refreshTrigger,
  onPostTypeChange
}) => {
  const [data, setData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [postTypeLabels, setPostTypeLabels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [config, setConfig] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Operation states
  const [modalState, setModalState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null); // { type: 'sync'|'clean'|'remove', postType, label }
  const [operationState, setOperationState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    status: 'idle',
    result: null
  });
  const [abortController, setAbortController] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Full data fetch on connection change
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selectedConnectionId) {
      fetchData();
    }
  }, [selectedConnectionId]);

  // Refresh validation counts when config changes (but don't remount)
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selectedConnectionId && refreshTrigger > 0) {
      refreshValidationData();
    }
  }, [refreshTrigger]);
  const fetchData = async () => {
    try {
      setLoading(true);
      // Fetch all post types, configuration, and validation data in parallel
      const [postTypesRes, configRes, validationRes] = await Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/post-types?connection=${selectedConnectionId}`
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/configuration?connection=${selectedConnectionId}`
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/validation/fast?connection=${selectedConnectionId}`
      })]);
      const allPostTypes = postTypesRes.post_types || [];
      const enabledTypes = configRes.configuration?.enabled_post_types || [];
      const validationData = validationRes.data?.posts || {};
      setConfig(configRes.configuration);

      // Store labels for use in modal/display
      const labels = {};
      allPostTypes.forEach(type => labels[type.name] = type.label);
      setPostTypeLabels(labels);

      // Map ALL available post types to table rows
      const mergedData = allPostTypes.map(type => ({
        name: type.name,
        label: type.label,
        enabled: enabledTypes.includes(type.name),
        validation: validationData[type.name] || {
          wordpress_count: 0,
          postgresql_count: 0,
          drift: 0,
          status: 'unknown'
        }
      }));
      setData(mergedData);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  const refreshValidationData = async () => {
    try {
      // Only refresh validation data, keep existing post type toggles
      const validationRes = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/validation/fast?connection=${selectedConnectionId}`
      });
      const validationData = validationRes.data?.posts || {};

      // Update only validation data in existing rows
      setData(prevData => prevData.map(type => ({
        ...type,
        validation: validationData[type.name] || {
          wordpress_count: 0,
          postgresql_count: 0,
          drift: 0,
          status: 'unknown'
        }
      })));
    } catch (err) {
      // Silently fail validation refresh, don't disrupt user
      console.error('Failed to refresh validation data:', err);
    }
  };
  const handleToggleSync = async (postType, checked) => {
    // Optimistic update
    const oldData = data;
    const newData = data.map(item => item.name === postType ? {
      ...item,
      enabled: checked
    } : item);
    setData(newData);
    try {
      const enabledTypes = newData.filter(i => i.enabled).map(i => i.name);

      // Fetch latest config to avoid overwriting other components' changes
      const latestConfigRes = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/configuration?connection=${selectedConnectionId}`
      });

      // Preserve ALL config settings from latest fetch
      const updatePayload = {
        ...latestConfigRes.configuration,
        enabled_post_types: enabledTypes
      };
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/configuration?connection=${selectedConnectionId}`,
        method: 'POST',
        data: updatePayload
      });
      if (response.success) {
        // Use the returned configuration to ensure state matches what was actually saved
        setConfig(response.configuration || {});

        // Update the data with the actual saved configuration
        const actualEnabledTypes = response.configuration?.enabled_post_types || enabledTypes;
        setData(prevData => prevData.map(item => ({
          ...item,
          enabled: actualEnabledTypes.includes(item.name)
        })));

        // Notify parent that post types changed (will trigger remount)
        if (onPostTypeChange) {
          onPostTypeChange();
        }
      } else {
        throw new Error(response.message || 'Update failed');
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to update configuration: ', 'gregius-data') + err.message);
      // Revert on error
      setData(oldData);
      fetchData();
    }
  };
  const handleBatchOperation = async (type, postType) => {
    let endpoint;
    let method = 'POST';
    if (type === 'sync') {
      endpoint = `/gg-data/v1/sync/batch-sync-post-type/${postType}`;
    } else if (type === 'clean') {
      endpoint = `/gg-data/v1/sync/post-type/${postType}/clean`;
    } else if (type === 'remove') {
      endpoint = `/gg-data/v1/sync/post-type/${postType}/orphans?connection=${selectedConnectionId}&batch_size=100`;
      method = 'DELETE';
    }
    const startTime = Date.now();
    const controller = new AbortController();
    setAbortController(controller);

    // Get original total for progress from modalState.data (already contains validation)
    // Fallback to searching in data array if needed
    const validationData = modalState?.data || data.find(d => d.name === postType)?.validation || {};
    const originalTotal = type === 'remove' ? Math.max(0, (validationData.postgresql_count || 0) - (validationData.wordpress_count || 0)) // Approx orphans
    : validationData.wordpress_count || 0;
    setOperationState({
      status: 'processing',
      type: type,
      rowType: postType,
      data: modalState.data,
      result: {
        processed: 0,
        total: originalTotal || 0,
        duration: 0,
        skipped: 0,
        failed: 0
      }
    });
    try {
      let hasMore = true;
      let totalProcessed = 0;
      let totalSkipped = 0;
      let totalFailed = 0;
      let batchCount = 0;
      let currentOffset = 0;
      while (hasMore && !controller.signal.aborted) {
        // For remove operations, we need to append the offset to the URL
        const currentEndpoint = type === 'remove' ? `${endpoint}&offset=${currentOffset}` : endpoint;
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: currentEndpoint,
          method: method,
          data: method === 'POST' ? {
            connection_name: selectedConnectionId,
            batch_size: 100,
            offset: batchCount * 100
          } : undefined,
          signal: controller.signal
        });
        if (response.success) {
          const batchProcessed = response.batch?.processed || response.processed || response.deleted || 0;
          const batchSkipped = response.batch?.skipped || 0;
          const batchFailed = response.batch?.failed || 0;
          const batchDeleted = response.deleted || 0;
          const batchChecked = response.processed || 0;
          totalProcessed += batchProcessed;
          totalSkipped += batchSkipped;
          totalFailed += batchFailed;
          batchCount++;

          // Update offset for remove operations: skip the records we kept (checked - deleted)
          if (type === 'remove') {
            currentOffset += batchChecked - batchDeleted;
          }
          setOperationState(prev => ({
            ...prev,
            result: {
              ...prev.result,
              processed: totalProcessed,
              skipped: totalSkipped,
              failed: totalFailed,
              duration: (Date.now() - startTime) / 1000
            }
          }));
          hasMore = response.batch?.has_more || response.has_more;
          if (hasMore) await new Promise(r => setTimeout(r, 100));
        } else {
          throw new Error(response.message || 'Operation failed');
        }
      }
      if (controller.signal.aborted) {
        setOperationState(prev => ({
          ...prev,
          status: 'stopped',
          result: {
            ...prev.result,
            duration: (Date.now() - startTime) / 1000
          }
        }));
      } else {
        setOperationState(prev => ({
          ...prev,
          status: 'completed',
          result: {
            ...prev.result,
            processed: totalProcessed,
            skipped: totalSkipped,
            failed: totalFailed,
            duration: (Date.now() - startTime) / 1000
          }
        }));
        fetchData(); // Refresh table data
      }
    } catch (err) {
      if (err.name === 'AbortError') {
        setOperationState(prev => ({
          ...prev,
          status: 'stopped',
          result: {
            ...prev.result,
            duration: (Date.now() - startTime) / 1000
          }
        }));
      } else {
        setError(err.message);
        setModalState(null);
      }
    } finally {
      setAbortController(null);
    }
  };
  const getStatusBadge = (status, drift) => {
    let color = 'green';
    let label = status;
    if (status === 'error' || Math.abs(drift) > 5) {
      color = 'red';
      label = 'Critical';
    } else if (status === 'warning' || Math.abs(drift) > 0) {
      color = 'orange';
      label = 'Warning';
    } else if (status === 'healthy') {
      color = 'green';
      label = 'Healthy';
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
      className: `components-badge is-${status === 'healthy' ? 'success' : status === 'warning' ? 'warning' : 'error'}`,
      children: label
    });
  };
  if (!selectedConnectionId) return null;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
      isRounded: false,
      className: "gg-content-sync-table",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
          level: 3,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Types', 'gregius-data')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
        children: [loading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {}) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            overflowX: 'auto'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Post Type', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  style: {
                    width: '100px'
                  },
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('WordPress', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('PostgreSQL', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Drift', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  style: {
                    width: '60px'
                  },
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Actions', 'gregius-data')
                })]
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tbody", {
              children: [data.map(row => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("strong", {
                    children: [row.label || row.name, " "]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                    checked: row.enabled,
                    onChange: checked => handleToggleSync(row.name, checked),
                    __nextHasNoMarginBottom: true
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: row.validation.wordpress_count?.toLocaleString()
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: row.validation.postgresql_count?.toLocaleString()
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: row.validation.drift_percentage !== undefined ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("span", {
                    className: `components-badge is-${row.validation.drift_percentage === 0 ? 'success' : Math.abs(row.validation.drift_percentage) > 10 ? 'error' : 'warning'}`,
                    children: [row.validation.drift_percentage > 0 ? '+' : '', Number(row.validation.drift_percentage).toFixed(1), "%"]
                  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                    className: "components-badge is-success",
                    children: "0.0%"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: row.enabled ? getStatusBadge(row.validation.status, row.validation.drift_percentage) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                    className: "components-badge",
                    children: "Disabled"
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DropdownMenu, {
                    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Actions', 'gregius-data'),
                    children: ({
                      onClose
                    }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.MenuItem, {
                        onClick: () => {
                          setModalState({
                            type: 'sync',
                            postType: row.name,
                            label: row.label || row.name,
                            data: row.validation
                          });
                          onClose();
                        },
                        disabled: !row.enabled,
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync %s', 'gregius-data'), row.label || row.name)
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.MenuItem, {
                        onClick: () => {
                          setModalState({
                            type: 'remove',
                            postType: row.name,
                            label: row.label || row.name,
                            data: row.validation
                          });
                          onClose();
                        },
                        className: "has-text-color has-vivid-red-color",
                        disabled: !row.validation.postgresql_count || row.validation.postgresql_count === 0,
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove Orphans', 'gregius-data')
                      })]
                    })
                  })
                })]
              }, row.name)), data.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tr", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  colSpan: "7",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No post types found.', 'gregius-data')
                })
              })]
            })]
          })
        }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "error",
          onRemove: () => setError(null),
          style: {
            marginTop: '1rem'
          },
          children: error
        })]
      })]
    }), modalState && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync %s', 'gregius-data') : modalState.type === 'clean' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Clean %s', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove %s Orphans', 'gregius-data'), modalState.label),
      onRequestClose: () => {
        if (operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped') {
          setModalState(null);
          setOperationState({
            status: 'idle',
            type: null,
            rowType: null,
            data: null,
            result: null
          });
        }
      },
      shouldCloseOnClickOutside: operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped',
      isDismissible: operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped',
      children: [operationState.status === 'idle' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This will process %d %s.', 'gregius-data'), modalState.data?.wordpress_count || 0, modalState.label.toLowerCase()) : modalState.type === 'clean' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This will clean %d %s by removing markup.', 'gregius-data'), modalState.data?.wordpress_count || 0, modalState.label.toLowerCase()) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This will permanently delete %s that exist in PostgreSQL but not in WordPress. This operation cannot be undone.', 'gregius-data'), modalState.label.toLowerCase())
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            display: 'flex',
            justifyContent: 'flex-start',
            gap: '12px',
            marginTop: '20px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            isDestructive: modalState.type === 'remove',
            onClick: () => handleBatchOperation(modalState.type, modalState.postType),
            children: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync', 'gregius-data') : modalState.type === 'clean' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Clean', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              setModalState(null);
              setOperationState({
                status: 'idle',
                type: null,
                rowType: null,
                data: null,
                result: null
              });
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cancel', 'gregius-data')
          })]
        })]
      }), operationState.status === 'processing' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Processed %d of %d records...', 'gregius-data'), (operationState.result?.processed || 0) + (operationState.result?.skipped || 0), operationState.result?.total || 0)
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            isBusy: true,
            disabled: true,
            children: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Syncing...', 'gregius-data') : modalState.type === 'clean' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cleaning...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Removing...', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              if (abortController) {
                abortController.abort();
                setOperationState(prev => ({
                  ...prev,
                  status: 'stopping'
                }));
              }
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Stop', 'gregius-data')
          })]
        })]
      }), operationState.status === 'stopping' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Stopping operation...', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            disabled: true,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Stopping...', 'gregius-data')
          })
        })]
      }), operationState.status === 'stopped' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "warning",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Operation stopped', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Processed %d of %d records (%s).', 'gregius-data'), operationState.result?.processed || 0, operationState.result?.total || 0, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              setModalState(null);
              setOperationState({
                status: 'idle',
                type: null,
                rowType: null,
                data: null,
                result: null
              });
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Close', 'gregius-data')
          })
        })]
      }), operationState.status === 'completed' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "success",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Operation completed successfully', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: modalState.type === 'sync' ? operationState.result?.skipped && operationState.result.skipped > 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d records synchronized, %d skipped (%s).', 'gregius-data'), operationState.result.processed || 0, operationState.result.skipped, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d records synchronized (%s).', 'gregius-data'), operationState.result?.processed || 0, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s') : modalState.type === 'clean' ? operationState.result?.skipped && operationState.result.skipped > 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d records cleaned, %d skipped (%s).', 'gregius-data'), operationState.result.processed || 0, operationState.result.skipped, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d records cleaned (%s).', 'gregius-data'), operationState.result?.processed || 0, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d orphan %s deleted (%s).', 'gregius-data'), operationState.result?.deleted || operationState.result?.processed || 0, modalState.label.toLowerCase(), typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              setModalState(null);
              setOperationState({
                status: 'idle',
                type: null,
                rowType: null,
                data: null,
                result: null
              });
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Close', 'gregius-data')
          })
        })]
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ContentSyncTable);

/***/ },

/***/ "./src/scripts/dashboard/components/sync/PostStatusesCard.js"
/*!*******************************************************************!*\
  !*** ./src/scripts/dashboard/components/sync/PostStatusesCard.js ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Post Statuses Configuration Card
 * 
 * Select which post statuses should be synchronized.
 */






const PostStatusesCard = ({
  selectedConnectionId,
  onConfigurationChange
}) => {
  const [selectedStatuses, setSelectedStatuses] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [fullConfig, setFullConfig] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const postStatuses = [{
    value: 'publish',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Published', 'gregius-data')
  }, {
    value: 'private',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Private', 'gregius-data')
  }, {
    value: 'draft',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Draft', 'gregius-data')
  }, {
    value: 'pending',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pending Review', 'gregius-data')
  }, {
    value: 'future',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Scheduled', 'gregius-data')
  }];
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selectedConnectionId) {
      fetchConfiguration();
    }
  }, [selectedConnectionId]);
  const fetchConfiguration = async () => {
    try {
      setIsLoading(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`
      });
      setFullConfig(response.configuration || {});
      const statuses = response.configuration?.enabled_statuses;
      // Ensure statuses is always an array (use empty array if not provided)
      setSelectedStatuses(Array.isArray(statuses) ? statuses : []);
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load configuration: ', 'gregius-data') + err.message);
    } finally {
      setIsLoading(false);
    }
  };
  const handleStatusToggle = async (status, checked) => {
    const oldStatuses = selectedStatuses;
    // Ensure selectedStatuses is an array before filtering
    const currentStatuses = Array.isArray(selectedStatuses) ? selectedStatuses : [];
    const updatedStatuses = checked ? [...currentStatuses, status] : currentStatuses.filter(s => s !== status);
    try {
      setIsSaving(true);
      setSelectedStatuses(updatedStatuses); // Optimistic
      setError(null);

      // Fetch latest config to avoid race conditions
      const latestConfigRes = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`
      });
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`,
        method: 'POST',
        data: {
          ...latestConfigRes.configuration,
          enabled_statuses: updatedStatuses
        }
      });
      if (response.success) {
        // Use the returned configuration to ensure state matches what was actually saved
        setFullConfig(response.configuration || {});
        const returnedStatuses = response.configuration?.enabled_statuses;
        // Ensure we always set an array
        setSelectedStatuses(Array.isArray(returnedStatuses) ? returnedStatuses : updatedStatuses);
        if (onConfigurationChange) onConfigurationChange();
      } else {
        throw new Error(response.message || 'Save failed');
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to save: ', 'gregius-data') + err.message);
      // Revert to old state
      setSelectedStatuses(oldStatuses);
      // Re-fetch to sync
      fetchConfiguration();
    } finally {
      setIsSaving(false);
    }
  };
  if (!selectedConnectionId) return null;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
    isRounded: false,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
        level: 3,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Post Statuses', 'gregius-data')
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
      children: isLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {}) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          className: "gg-data-sync-section",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "description",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select which post statuses should be synchronized:', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            className: "gg-data-toggle-grid",
            style: {
              display: 'flex',
              flexDirection: 'column',
              gap: '.5rem'
            },
            children: postStatuses.map(status => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
              label: status.label,
              checked: selectedStatuses.includes(status.value),
              onChange: checked => handleStatusToggle(status.value, checked),
              disabled: isSaving,
              __nextHasNoMarginBottom: true
            }, status.value))
          })]
        }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "error",
          onRemove: () => setError(null),
          style: {
            marginTop: '1rem'
          },
          children: error
        })]
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (PostStatusesCard);

/***/ },

/***/ "./src/scripts/dashboard/components/sync/RealTimeSyncCard.js"
/*!*******************************************************************!*\
  !*** ./src/scripts/dashboard/components/sync/RealTimeSyncCard.js ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Real-time Sync Configuration Card
 * 
 * Global toggle for real-time synchronization.
 */






const RealTimeSyncCard = ({
  selectedConnectionId,
  onConfigurationChange
}) => {
  const [syncEnabled, setSyncEnabled] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // We need to store other config parts to avoid overwriting them
  const [fullConfig, setFullConfig] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selectedConnectionId) {
      fetchConfiguration();
    }
  }, [selectedConnectionId]);
  const fetchConfiguration = async () => {
    try {
      setIsLoading(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`
      });
      setFullConfig(response.configuration || {});
      setSyncEnabled(response.configuration?.real_time_sync || false);
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load configuration: ', 'gregius-data') + err.message);
    } finally {
      setIsLoading(false);
    }
  };
  const handleSyncEnabledToggle = async checked => {
    try {
      setIsSaving(true);
      setSyncEnabled(checked); // Optimistic update
      setError(null);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/sync/configuration?connection=${encodeURIComponent(selectedConnectionId)}`,
        method: 'POST',
        data: {
          ...fullConfig,
          real_time_sync: checked
        }
      });
      if (response.success) {
        // Use the returned configuration to ensure state matches what was actually saved
        setFullConfig(response.configuration || {});
        setSyncEnabled(response.configuration?.real_time_sync ?? checked);
        if (onConfigurationChange) onConfigurationChange();
      }
    } catch (err) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to save: ', 'gregius-data') + err.message);
      setSyncEnabled(!checked); // Revert
    } finally {
      setIsSaving(false);
    }
  };
  if (!selectedConnectionId) return null;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
    isRounded: false,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
        level: 3,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Real-time', 'gregius-data')
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
      children: isLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {}) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Enable', 'gregius-data'),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Automatically synchronize content changes.', 'gregius-data'),
          checked: syncEnabled,
          onChange: handleSyncEnabledToggle,
          disabled: isSaving,
          __nextHasNoMarginBottom: true
        }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "error",
          onRemove: () => setError(null),
          style: {
            marginTop: '1rem'
          },
          children: error
        })]
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RealTimeSyncCard);

/***/ },

/***/ "./src/scripts/dashboard/components/sync/RetryQueueCard.js"
/*!*****************************************************************!*\
  !*** ./src/scripts/dashboard/components/sync/RetryQueueCard.js ***!
  \*****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






/**
 * Retry Queue Status Card Component
 * 
 * Displays retry queue metrics, pending retries, and dead letter queue items.
 * Provides manual retry and clear functionality.
 */

const RetryQueueCard = ({
  selectedConnectionId
}) => {
  // Show empty state if no connection selected
  if (!selectedConnectionId) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
      status: "warning",
      isDismissible: false,
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please select a connection to begin.', 'gregius-data')
    });
  }
  const [queueStatus, setQueueStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [refreshInterval, setRefreshInterval] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  /**
   * Fetch queue status from REST API
   */
  const fetchQueueStatus = async () => {
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/gg-data/v1/sync/retry-queue',
        method: 'GET'
      });
      if (response.success) {
        setQueueStatus(response.data);
        setError(null);
      }
    } catch (err) {
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to fetch retry queue status', 'gregius-data'));
    } finally {
      setLoading(false);
    }
  };

  /**
   * Manual retry of dead letter item
   */
  const handleManualRetry = async index => {
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/retry-queue/retry/${index}`,
        method: 'POST'
      });
      if (response.success) {
        // Refresh queue status after retry
        await fetchQueueStatus();
      }
    } catch (err) {
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to retry item', 'gregius-data'));
    }
  };

  /**
   * Clear dead letter queue
   */
  const handleClearDeadLetter = async () => {
    if (!window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Are you sure you want to clear all permanently failed items?', 'gregius-data'))) {
      return;
    }
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/gg-data/v1/sync/retry-queue/clear',
        method: 'DELETE'
      });
      if (response.success) {
        // Refresh queue status after clearing
        await fetchQueueStatus();
      }
    } catch (err) {
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to clear dead letter queue', 'gregius-data'));
    }
  };

  /**
   * Setup auto-refresh on mount and when connection changes
   */
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    // Initial fetch
    fetchQueueStatus();

    // Auto-refresh every 30 seconds
    const interval = setInterval(fetchQueueStatus, 30000);
    setRefreshInterval(interval);

    // Cleanup on unmount or connection change
    return () => {
      if (interval) {
        clearInterval(interval);
      }
    };
  }, [selectedConnectionId]);
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "retry-queue-card",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        className: "retry-queue-loading",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Loading queue status...', 'gregius-data')
        })]
      })
    });
  }
  if (error) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "retry-queue-card retry-queue-error",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "error",
        isDismissible: false,
        children: error
      })
    });
  }
  const {
    pending_retries,
    failed_permanently,
    items,
    dead_letter_items
  } = queueStatus || {};

  // Combine items for display: pending items + dead letter items with source tracking
  const allItems = [...(items || []).map((item, idx) => ({
    ...item,
    source: 'pending',
    originalIndex: idx
  })), ...(dead_letter_items || []).map((item, idx) => ({
    ...item,
    source: 'dead_letter',
    originalIndex: idx
  }))];
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
    isRounded: false,
    className: "gg-search-health-card retry-queue-card",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        width: '100%'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
          level: 3,
          style: {
            margin: 0
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Retry Queue', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          className: "description",
          style: {
            margin: 0
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Automatic retry with exponential backoff for transient sync errors', 'gregius-data')
        })]
      }), failed_permanently > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "secondary",
        onClick: handleClearDeadLetter,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Clear Dead Letter Queue', 'gregius-data')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
        columns: 3,
        gap: 4,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pending Retries:', 'gregius-data')
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
            className: "components-badge is-info",
            children: pending_retries || 0
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed Permanently:', 'gregius-data')
          }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
            className: "components-badge is-info",
            children: failed_permanently || 0
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("hr", {
        style: {
          marginTop: '16px'
        }
      }), allItems.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
          level: 4,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Queue Items', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Operation', 'gregius-data')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Entity ID', 'gregius-data')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Attempt', 'gregius-data')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status', 'gregius-data')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Last Error', 'gregius-data')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                style: {
                  width: '60px'
                },
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Actions', 'gregius-data')
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tbody", {
            children: allItems.map((item, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: item.operation_type
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: item.entity_id
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: item.attempt_count || 0
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                  className: `components-badge is-${item.source === 'pending' ? 'info' : 'warning'}`,
                  children: item.source === 'pending' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pending', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed', 'gregius-data')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                className: "error-cell",
                title: item.last_error,
                children: item.last_error ? item.last_error.substring(0, 50) + '...' : '-'
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: item.source === 'dead_letter' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DropdownMenu, {
                  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Actions', 'gregius-data'),
                  children: ({
                    onClose
                  }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.MenuItem, {
                    onClick: () => {
                      handleManualRetry(item.originalIndex);
                      onClose();
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Retry', 'gregius-data')
                  })
                })
              })]
            }, `${item.source}-${index}`))
          })]
        })]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
        columns: 1,
        gap: 4,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No retry queue items.', 'gregius-data')
          })
        })
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RetryQueueCard);

/***/ },

/***/ "./src/scripts/dashboard/components/sync/TermsSyncCard.js"
/*!****************************************************************!*\
  !*** ./src/scripts/dashboard/components/sync/TermsSyncCard.js ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Terms Sync Card Component
 * 
 * Dedicated card for managing Terms (Categories, Tags) synchronization.
 * Handles display of term counts and sync actions.
 */







const TermsSyncCard = ({
  selectedConnectionId,
  onSyncComplete
}) => {
  const [stats, setStats] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Operation states
  const [modalState, setModalState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null); // { type: 'sync'|'remove' }
  const [operationState, setOperationState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    status: 'idle',
    // idle|processing|stopping|completed
    result: null
  });
  const [abortController, setAbortController] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selectedConnectionId) {
      fetchTermsStats();
    }
  }, [selectedConnectionId]);
  const fetchTermsStats = async () => {
    try {
      setLoading(true);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/sync/validation/fast?connection=${selectedConnectionId}`
      });
      if (response.success && response.data?.terms) {
        setStats(response.data.terms);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  const handleBatchOperation = async type => {
    const endpoint = type === 'sync' ? '/gg-data/v1/sync/batch-sync-terms' : `/gg-data/v1/sync/terms/orphans?connection=${selectedConnectionId}&batch_size=100`;
    const method = type === 'sync' ? 'POST' : 'DELETE';
    const startTime = Date.now();
    const controller = new AbortController();
    setAbortController(controller);
    const originalTotal = type === 'sync' ? stats.wordpress_count : stats.postgresql_count - stats.wordpress_count; // Approx orphans

    setOperationState({
      status: 'processing',
      type: type,
      rowType: 'terms',
      data: stats,
      result: {
        processed: 0,
        total: originalTotal || 0,
        duration: 0,
        skipped: 0,
        failed: 0
      }
    });
    try {
      let hasMore = true;
      let totalProcessed = 0;
      let totalSkipped = 0;
      let totalFailed = 0;
      let batchCount = 0;
      let currentOffset = 0;
      while (hasMore && !controller.signal.aborted) {
        // For remove operations, we need to append the offset to the URL
        const currentEndpoint = type === 'remove' ? `${endpoint}&offset=${currentOffset}` : endpoint;
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: currentEndpoint,
          method: method,
          data: type === 'sync' ? {
            connection_name: selectedConnectionId,
            batch_size: 500,
            offset: batchCount * 500
          } : undefined,
          signal: controller.signal
        });
        if (response.success) {
          const batchProcessed = response.batch?.processed || response.processed || response.deleted || 0;
          const batchSkipped = response.batch?.skipped || 0;
          const batchFailed = response.batch?.failed || 0;
          const batchDeleted = response.deleted || 0;
          const batchChecked = response.processed || 0;
          totalProcessed += batchProcessed;
          totalSkipped += batchSkipped;
          totalFailed += batchFailed;
          batchCount++;

          // Update offset for remove operations: skip the records we kept (checked - deleted)
          if (type === 'remove') {
            currentOffset += batchChecked - batchDeleted;
          }
          setOperationState(prev => ({
            ...prev,
            result: {
              ...prev.result,
              processed: totalProcessed,
              skipped: totalSkipped,
              failed: totalFailed,
              duration: (Date.now() - startTime) / 1000
            }
          }));
          hasMore = response.batch?.has_more || response.has_more;
          if (hasMore) await new Promise(r => setTimeout(r, 100));
        } else {
          throw new Error(response.message || 'Operation failed');
        }
      }
      if (controller.signal.aborted) {
        setOperationState(prev => ({
          ...prev,
          status: 'stopped',
          result: {
            ...prev.result,
            duration: (Date.now() - startTime) / 1000
          }
        }));
      } else {
        setOperationState(prev => ({
          ...prev,
          status: 'completed',
          result: {
            ...prev.result,
            processed: totalProcessed,
            skipped: totalSkipped,
            failed: totalFailed,
            duration: (Date.now() - startTime) / 1000
          }
        }));
        fetchTermsStats();
        if (onSyncComplete) onSyncComplete();
      }
    } catch (err) {
      if (err.name === 'AbortError') {
        setOperationState(prev => ({
          ...prev,
          status: 'stopped',
          result: {
            ...prev.result,
            duration: (Date.now() - startTime) / 1000
          }
        }));
      } else {
        setError(err.message);
        setModalState(null);
      }
    } finally {
      setAbortController(null);
    }
  };
  if (!selectedConnectionId) return null;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
      isRounded: false,
      className: "gg-terms-sync-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
          level: 3,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Taxonomies', 'gregius-data')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
        children: [loading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {}) : stats ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            overflowX: 'auto'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Item', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('WordPress', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('PostgreSQL', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  style: {
                    width: '60px'
                  },
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Actions', 'gregius-data')
                })]
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tbody", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Terms & Taxonomies', 'gregius-data')
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: stats.wordpress_count?.toLocaleString()
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: stats.postgresql_count?.toLocaleString()
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                    className: `components-badge is-${stats.status === 'healthy' ? 'success' : 'warning'}`,
                    children: stats.status || 'Unknown'
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DropdownMenu, {
                    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Actions', 'gregius-data'),
                    children: ({
                      onClose
                    }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.MenuItem, {
                        onClick: () => {
                          setModalState({
                            type: 'sync',
                            data: stats
                          });
                          onClose();
                        },
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync Terms', 'gregius-data')
                      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.MenuItem, {
                        onClick: () => {
                          setModalState({
                            type: 'remove',
                            data: stats
                          });
                          onClose();
                        },
                        className: "has-text-color has-vivid-red-color",
                        disabled: !stats.postgresql_count || stats.postgresql_count === 0,
                        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove Orphans', 'gregius-data')
                      })]
                    })
                  })
                })]
              })
            })]
          })
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No term data available.', 'gregius-data')
        }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "error",
          onRemove: () => setError(null),
          style: {
            marginTop: '1rem'
          },
          children: error
        })]
      })]
    }), modalState && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync Terms', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove Orphan Terms', 'gregius-data'),
      onRequestClose: () => {
        if (operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped') {
          setModalState(null);
          setOperationState({
            status: 'idle',
            type: null,
            rowType: null,
            data: null,
            result: null
          });
        }
      },
      shouldCloseOnClickOutside: operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped',
      isDismissible: operationState.status === 'idle' || operationState.status === 'completed' || operationState.status === 'stopped',
      children: [operationState.status === 'idle' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This will process %d terms.', 'gregius-data'), modalState.data?.wordpress_count || 0) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This will permanently delete terms that exist in PostgreSQL but not in WordPress. This operation cannot be undone.', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            display: 'flex',
            justifyContent: 'flex-start',
            gap: '12px',
            marginTop: '20px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            isDestructive: modalState.type === 'remove',
            onClick: () => handleBatchOperation(modalState.type),
            children: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sync', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              setModalState(null);
              setOperationState({
                status: 'idle',
                type: null,
                rowType: null,
                data: null,
                result: null
              });
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cancel', 'gregius-data')
          })]
        })]
      }), operationState.status === 'processing' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Processed %d of %d records...', 'gregius-data'), (operationState.result?.processed || 0) + (operationState.result?.skipped || 0), operationState.result?.total || 0)
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            isBusy: true,
            disabled: true,
            children: modalState.type === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Syncing...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Removing...', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              if (abortController) {
                abortController.abort();
                setOperationState(prev => ({
                  ...prev,
                  status: 'stopping'
                }));
              }
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Stop', 'gregius-data')
          })]
        })]
      }), operationState.status === 'stopping' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Stopping operation...', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            disabled: true,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Stopping...', 'gregius-data')
          })
        })]
      }), operationState.status === 'stopped' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "warning",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Operation stopped', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Processed %d of %d records (%s).', 'gregius-data'), operationState.result?.processed || 0, operationState.result?.total || 0, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              setModalState(null);
              setOperationState({
                status: 'idle',
                type: null,
                rowType: null,
                data: null,
                result: null
              });
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Close', 'gregius-data')
          })
        })]
      }), operationState.status === 'completed' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "success",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Operation completed successfully', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: modalState.type === 'sync' ? operationState.result?.skipped && operationState.result.skipped > 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d records synchronized, %d skipped (%s).', 'gregius-data'), operationState.result.processed || 0, operationState.result.skipped, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d records synchronized (%s).', 'gregius-data'), operationState.result?.processed || 0, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d orphan terms deleted (%s).', 'gregius-data'), operationState.result?.deleted || operationState.result?.processed || 0, typeof operationState.result?.duration === 'number' ? operationState.result.duration.toFixed(2) + 's' : operationState.result?.duration || '0s')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-start',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "link",
            onClick: () => {
              setModalState(null);
              setOperationState({
                status: 'idle',
                type: null,
                rowType: null,
                data: null,
                result: null
              });
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Close', 'gregius-data')
          })
        })]
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (TermsSyncCard);

/***/ },

/***/ "./src/scripts/dashboard/components/vectors/APIEmbeddingCard.js"
/*!**********************************************************************!*\
  !*** ./src/scripts/dashboard/components/vectors/APIEmbeddingCard.js ***!
  \**********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _BatchDeleteModal__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./BatchDeleteModal */ "./src/scripts/dashboard/components/vectors/BatchDeleteModal.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * API Embedding Card Component
 *
 * Card component for API-based embedding models (OpenAI, Voyage AI, etc.)
 * showing generation status, token tracking, and batch processing actions.
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/assets/src/scripts/dashboard/components/vectors
 * @since      1.0.0
 */








/**
 * APIEmbeddingCard Component
 *
 * @param {Object}   props            Props object.
 * @param {Object}   props.model      Model configuration.
 * @param {string}   props.connection Connection name.
 * @param {Function} props.onRemove   Callback when remove is clicked (receives modelKey, vectorCount).
 * @param {Function} props.onRefresh  Callback to refresh parent data.
 * @return {JSX.Element} Card component.
 */

const APIEmbeddingCard = ({
  model,
  connection,
  onRemove,
  onRefresh
}) => {
  // Unified operation state for all actions (generate, regenerate)
  const [operationState, setOperationState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    status: 'idle',
    // 'idle' | 'processing' | 'completed' | 'stopped' | 'error'
    type: null,
    // 'generate' | 'regenerate'
    data: null,
    result: null
  });

  // Modal state
  const [modalState, setModalState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null); // { type: 'generate' | 'regenerate', data: ... }

  // Delete modal state
  const [showDeleteModal, setShowDeleteModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  // Abort controller for stopping batch operations
  const [abortController, setAbortController] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Vector status (fetched independently)
  const [vectorStatus, setVectorStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  /**
   * Fetch vector status for this model
   */
  const fetchVectorStatus = async () => {
    // Guard: Don't fetch if model data is incomplete
    if (!model?.model_key || !connection) {
      return;
    }
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/vectors/status?connection_name=${connection}&model_key=${model.model_key}`
      });
      if (response.success && response.status) {
        setVectorStatus(response.status);
      }
    } catch (err) {
      console.error('Failed to fetch vector status:', err);
      // Silently fail - the card will still show without vector status
    }
  };

  // Fetch vector status on mount and when operation completes
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchVectorStatus();
  }, [model?.model_key, connection]);

  /**
   * Handle Generate Vectors action
   */
  const handleGenerateClick = () => {
    setModalState({
      type: 'generate',
      data: vectorStatus
    });
    setOperationState({
      status: 'idle',
      type: 'generate',
      data: vectorStatus,
      result: null
    });
  };

  /**
   * Handle Regenerate All Vectors action
   */
  const handleRegenerateClick = () => {
    setModalState({
      type: 'regenerate',
      data: vectorStatus
    });
    setOperationState({
      status: 'idle',
      type: 'regenerate',
      data: vectorStatus,
      result: null
    });
  };

  /**
   * Start batch vector generation/regeneration loop
   * Processes posts in batches until all complete, accumulating tokens
   */
  const startBatchProcessing = async (regenerateSince = null) => {
    const startTime = Date.now();
    const totalPending = regenerateSince ? vectorStatus?.total_posts || 0 : vectorStatus?.posts_pending_vectors || 0;
    const controller = new AbortController();
    setAbortController(controller);
    setOperationState({
      status: 'processing',
      type: regenerateSince ? 'regenerate' : 'generate',
      data: vectorStatus,
      result: {
        processed: 0,
        total: totalPending,
        totalTokens: 0,
        duration: 0
      }
    });
    try {
      let hasMore = true;
      let totalProcessed = 0;
      let totalFailed = 0;
      let totalTokens = 0;

      // Batch processing loop
      while (hasMore && !controller.signal.aborted) {
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
          path: '/gg-data/v1/vectors/batch-generate',
          method: 'POST',
          data: {
            connection_name: connection,
            batch_size: 10,
            // Smaller batches for API calls
            regenerate_since: regenerateSince,
            model_key: model.model_key
          },
          signal: controller.signal
        });
        if (!response.success || !response.batch) {
          throw new Error(response.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Batch processing failed', 'gregius-data'));
        }

        // Update totals (accumulate tokens, not cost)
        totalProcessed += response.batch.processed || 0;
        totalFailed += response.batch.failed || 0;
        totalTokens += response.batch.total_tokens || 0;

        // Update progress
        const duration = (Date.now() - startTime) / 1000;
        setOperationState(prev => ({
          ...prev,
          result: {
            processed: totalProcessed,
            total: totalPending,
            failed: totalFailed,
            totalTokens,
            duration
          }
        }));

        // Check if more batches remain
        hasMore = response.batch.has_more;

        // Short delay between batches (100ms) for UI updates
        if (hasMore) {
          await new Promise(resolve => setTimeout(resolve, 100));
        }
      }

      // Check if aborted
      if (controller.signal.aborted) {
        const duration = (Date.now() - startTime) / 1000;
        setOperationState(prev => ({
          ...prev,
          status: 'stopped',
          result: {
            ...prev.result,
            duration
          }
        }));
      } else {
        // All batches complete
        const duration = (Date.now() - startTime) / 1000;
        setOperationState(prev => ({
          ...prev,
          status: 'completed',
          result: {
            processed: totalProcessed,
            total: totalPending,
            failed: totalFailed,
            totalTokens,
            duration
          }
        }));

        // Refresh vector status and parent data
        await fetchVectorStatus();
        if (onRefresh) {
          await onRefresh();
        }
      }
    } catch (err) {
      if (err.name === 'AbortError') {
        const duration = (Date.now() - startTime) / 1000;
        setOperationState(prev => ({
          ...prev,
          status: 'stopped',
          result: {
            ...prev.result,
            duration
          }
        }));
      } else {
        setOperationState(prev => ({
          ...prev,
          status: 'error',
          result: {
            ...prev.result,
            error: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Batch processing failed', 'gregius-data')
          }
        }));
      }
    }
  };

  /**
   * Stop batch processing
   */
  const handleStop = () => {
    if (abortController) {
      abortController.abort();
    }
  };

  /**
   * Close modal and reset operation state
   */
  const handleCloseModal = () => {
    // Don't allow closing while processing
    if (operationState.status === 'processing') {
      return;
    }
    setModalState(null);
    setOperationState({
      status: 'idle',
      type: null,
      data: null,
      result: null
    });
  };

  /**
   * Handle remove model
   */
  const handleRemove = () => {
    // Confirm before removing
    if (!window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Are you sure you want to remove this model? Vector data will remain in the database.', 'gregius-data'))) {
      return;
    }
    const count = vectorStatus?.posts_with_vectors || 0;
    onRemove(model.model_key, count);
  };

  /**
   * Handle delete vectors action
   */
  const handleDeleteVectors = () => {
    const deletableVectors = vectorStatus?.actual_vectors ?? vectorStatus?.posts_with_vectors ?? 0;
    if (deletableVectors === 0) {
      alert((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No vectors to delete.', 'gregius-data'));
      return;
    }
    setShowDeleteModal(true);
  };

  /**
   * Handle successful vector deletion
   */
  const handleDeleteSuccess = deleted => {
    setShowDeleteModal(false);
    // Refresh vector status and parent data
    fetchVectorStatus();
    if (onRefresh) {
      onRefresh();
    }
  };

  // Guard against undefined model data
  if (!model || !model.provider) {
    return null;
  }

  // Vector status helpers
  const totalPosts = vectorStatus?.total_posts || 0;
  const postsWithVectors = vectorStatus?.posts_with_vectors || 0;
  const postsPending = vectorStatus?.posts_pending_vectors || 0;
  const postsOutdated = vectorStatus?.posts_with_outdated_vectors || 0;
  const totalDrift = postsPending + postsOutdated; // Total posts needing vectors (pending + outdated)
  const driftPercentage = vectorStatus?.drift_percentage || 0;
  const percentage = totalPosts > 0 ? Math.round(postsWithVectors / totalPosts * 100) : 0;
  const actualVectors = vectorStatus?.actual_vectors || 0;
  const deletableVectors = vectorStatus?.actual_vectors ?? vectorStatus?.posts_with_vectors ?? 0;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      className: "gg-data-vector-card gg-data-api-embedding-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'flex-start',
            width: '100%'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "gg-data-card-header-content",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
              style: {
                display: 'flex',
                flexDirection: 'row',
                alignItems: 'center',
                gap: '.25em'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("h3", {
                style: {
                  margin: '0'
                },
                children: [model.provider_model_id || model.model_key, ' ']
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                className: "components-badge is-info",
                children: model.provider === 'internal' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Internal (Free)', 'gregius-data') : model.provider.toUpperCase()
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
              style: {
                margin: '0'
              },
              children: [model.dimensions || 0, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)(' dimensions', 'gregius-data')]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.DropdownMenu, {
            icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Vector Actions', 'gregius-data'),
            controls: [{
              title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Generate Vectors', 'gregius-data'),
              onClick: handleGenerateClick,
              disabled: postsPending === 0
            }, {
              title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Regenerate All Vectors', 'gregius-data'),
              onClick: handleRegenerateClick
            }, {
              title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete Vectors', 'gregius-data'),
              onClick: handleDeleteVectors,
              disabled: deletableVectors === 0
            }, {
              title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove Model', 'gregius-data'),
              onClick: handleRemove
            }]
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: [error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          className: "gg-data-error-notice",
          style: {
            padding: '8px 12px',
            marginBottom: '12px',
            background: '#fef7f7',
            border: '1px solid #d63638',
            borderRadius: '4px',
            color: '#d63638'
          },
          children: error
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "gg-data-vector-stats",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            style: {
              marginBottom: '20px'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "gg-data-stat-label",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Provider:', 'gregius-data')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "gg-data-stat-value",
              children: model.provider
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            style: {
              marginBottom: '20px'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h4", {
              className: "gg-data-stat-label",
              style: {
                margin: '0 0 8px',
                fontSize: '13px',
                fontWeight: 600
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Vector Status', 'gregius-data')
            }), vectorStatus !== null ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
              style: {
                fontSize: '13px',
                lineHeight: '1.8'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Processed: ', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
                  children: [totalPosts.toLocaleString(), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('posts', 'gregius-data')]
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Vectorized: ', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
                  children: [postsWithVectors.toLocaleString(), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('posts', 'gregius-data')]
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Drift: ', 'gregius-data')
                }), postsWithVectors === 0 && totalPosts > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                  className: "components-badge is-info",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Not generated', 'gregius-data')
                }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                  className: `components-badge is-${driftPercentage === 0 ? 'success' : Math.abs(driftPercentage) > 10 ? 'error' : 'warning'}`,
                  children: totalPosts > 0 ? `${driftPercentage > 0 ? '+' : ''}${Number(driftPercentage).toFixed(1)}%` : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('N/A', 'gregius-data')
                })]
              })]
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {})]
          })]
        })]
      })]
    }), modalState && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Modal, {
      title: modalState.type === 'generate' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Generate Vectors', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Regenerate All Vectors', 'gregius-data'),
      onRequestClose: handleCloseModal,
      className: "gg-data-vector-modal",
      shouldCloseOnClickOutside: operationState.status !== 'processing',
      shouldCloseOnEsc: operationState.status !== 'processing',
      children: [operationState.status === 'idle' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          children: modalState.type === 'generate' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Generate vectors for %d posts using %s?', 'gregius-data'), postsPending, model.provider_model_id) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Regenerate vectors for all %d posts using %s?', 'gregius-data'), totalPosts, model.provider_model_id)
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-end',
            marginTop: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            onClick: handleCloseModal,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            isPrimary: true,
            onClick: () => startBatchProcessing(modalState.type === 'regenerate' ? new Date().toISOString() : null),
            children: modalState.type === 'generate' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Generate', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Regenerate All', 'gregius-data')
          })]
        })]
      }), operationState.status === 'processing' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            marginBottom: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              fontSize: '14px',
              marginBottom: '8px',
              color: '#1e1e1e'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Processing %d of %d (%d%%)', 'gregius-data'), operationState.result?.processed || 0, operationState.result?.total || 0, operationState.result?.total > 0 ? Math.round((operationState.result?.processed || 0) / operationState.result.total * 100) : 0)
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              fontSize: '13px',
              color: '#757575'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('%s tokens', 'gregius-data'), (operationState.result?.totalTokens || 0).toLocaleString())
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            display: 'flex',
            justifyContent: 'flex-end'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            isDestructive: true,
            onClick: handleStop,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Stop', 'gregius-data')
          })
        })]
      }), operationState.status === 'completed' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
          status: "success",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Generated %d vectors (%s tokens)', 'gregius-data'), operationState.result?.processed || 0, (operationState.result?.totalTokens || 0).toLocaleString())
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            display: 'flex',
            justifyContent: 'flex-end',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            isPrimary: true,
            onClick: handleCloseModal,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gregius-data')
          })
        })]
      }), operationState.status === 'stopped' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
          status: "warning",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Stopped. Processed %d vectors (%s tokens)', 'gregius-data'), operationState.result?.processed || 0, (operationState.result?.totalTokens || 0).toLocaleString())
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            display: 'flex',
            justifyContent: 'flex-end',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            isPrimary: true,
            onClick: handleCloseModal,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gregius-data')
          })
        })]
      }), operationState.status === 'error' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
          status: "error",
          isDismissible: false,
          children: operationState.result?.error || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('An error occurred', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            display: 'flex',
            justifyContent: 'flex-end',
            marginTop: '16px'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            isPrimary: true,
            onClick: handleCloseModal,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gregius-data')
          })
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_BatchDeleteModal__WEBPACK_IMPORTED_MODULE_5__["default"], {
      isOpen: showDeleteModal,
      modelKey: model.model_key,
      connectionName: connection,
      totalVectors: deletableVectors,
      batchSize: 500,
      onClose: () => setShowDeleteModal(false),
      onSuccess: handleDeleteSuccess
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (APIEmbeddingCard);

/***/ },

/***/ "./src/scripts/dashboard/components/vectors/AddModelModal.js"
/*!*******************************************************************!*\
  !*** ./src/scripts/dashboard/components/vectors/AddModelModal.js ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Add Model Modal Component
 *
 * Modal for adding global embedding models to a specific connection.
 * Shows all available models with checkmarks for already-added ones.
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/assets/src/scripts/dashboard/components/vectors
 * @since      1.0.0
 */






/**
 * AddModelModal Component
 *
 * @param {Object}   props                Props object.
 * @param {string}   props.connection     Connection name.
 * @param {Array}    props.existingModels Array of models already added to connection.
 * @param {Function} props.onAdd          Callback when model is added (receives modelKey).
 * @param {Function} props.onClose        Callback when modal is closed.
 * @return {JSX.Element} Modal component.
 */

const AddModelModal = ({
  connection,
  existingModels,
  onAdd,
  onClose
}) => {
  const [availableModels, setAvailableModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [selectedModel, setSelectedModel] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchAvailableModels();
  }, []);

  /**
   * Fetch all global embedding models from registry
   */
  const fetchAvailableModels = async () => {
    setIsLoading(true);
    setError(null);
    try {
      // Fetch all global embedding models.
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: '/gg-data/v1/models?type=embeddings&status=active'
      });
      if (response.success && response.data) {
        setAvailableModels(response.data);
      } else {
        setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to fetch models', 'gregius-data'));
      }
    } catch (err) {
      console.error('Failed to fetch models:', err);
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load models. Please try again.', 'gregius-data'));
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Get select options with checkmarks for already-added models
   */
  const getModelOptions = () => {
    const existingKeys = existingModels.map(m => m.model_key);
    return [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select a model...', 'gregius-data'),
      value: ''
    }, ...availableModels.map(model => ({
      label: `${existingKeys.includes(model.model_key) ? '✓ ' : ''}${model.provider} - ${model.provider_model_id} (${model.dimensions}D)`,
      value: model.model_key,
      disabled: existingKeys.includes(model.model_key)
    }))];
  };

  /**
   * Handle add button click
   */
  const handleAdd = () => {
    if (selectedModel) {
      onAdd(selectedModel);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Embedding Model', 'gregius-data'),
    onRequestClose: onClose,
    className: "gg-data-add-model-modal",
    children: isLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gg-data-modal-loading",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading available models...', 'gregius-data')
      })]
    }) : error ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "gg-data-modal-error",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        style: {
          color: '#d63638'
        },
        children: error
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        onClick: fetchAvailableModels,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Retry', 'gregius-data')
      })]
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select Model', 'gregius-data'),
        value: selectedModel,
        onChange: setSelectedModel,
        options: getModelOptions()
      }), availableModels.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        style: {
          marginTop: '16px',
          color: '#757575'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No embedding models found. Add embedding models in the Models tab.', 'gregius-data')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          justifyContent: 'flex-start',
          marginTop: '20px',
          gap: '8px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          isPrimary: true,
          onClick: handleAdd,
          disabled: !selectedModel,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Model', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          onClick: onClose,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gregius-data')
        })]
      })]
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AddModelModal);

/***/ },

/***/ "./src/scripts/dashboard/components/vectors/BatchDeleteModal.js"
/*!**********************************************************************!*\
  !*** ./src/scripts/dashboard/components/vectors/BatchDeleteModal.js ***!
  \**********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Batch Delete Modal Component
 *
 * Generic modal for batch vector deletion with:
 * - Progress tracking (processed / total)
 * - Time estimates and elapsed duration
 * - Error handling and retry logic
 * - Cancellation support via AbortController
 *
 * Reusable for internal hashing, API embeddings, and future post deletion.
 *
 * @package    Gregius_Data
 * @subpackage Gregius_Data/assets/src/scripts/dashboard/components/vectors
 * @since      1.0.0
 */






/**
 * BatchDeleteModal Component
 *
 * @param {Object}   props                    Props object.
 * @param {boolean}  props.isOpen             Whether modal is open.
 * @param {string}   props.modelKey           Model key being deleted.
 * @param {string}   props.connectionName     Database connection name.
 * @param {number}   props.totalVectors       Total vectors to delete (for progress).
 * @param {number}   props.batchSize          Vectors per request (default 500).
 * @param {Function} props.onClose            Callback when modal closes.
 * @param {Function} props.onSuccess          Callback after successful deletion.
 * @return {JSX.Element} Modal component or null.
 */

const BatchDeleteModal = ({
  isOpen,
  modelKey,
  connectionName,
  totalVectors = 0,
  batchSize = 500,
  mode = 'vectors',
  onClose,
  onSuccess
}) => {
  // Batch delete operation state
  const [deleteState, setDeleteState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    status: 'idle',
    // 'idle' | 'processing' | 'completed' | 'error' | 'stopped'
    processed: 0,
    // Vectors deleted so far
    total: totalVectors,
    // Total to delete
    offset: 0,
    // Current pagination cursor
    errors: [],
    // Batch-level errors
    duration: 0,
    // Elapsed time (ms)
    abortController: null // For cancellation
  });

  // Keep total in sync with latest card status when modal opens.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!isOpen) {
      return;
    }
    setDeleteState(prev => ({
      ...prev,
      total: totalVectors
    }));
  }, [isOpen, totalVectors]);

  /**
   * Execute batch delete loop
   */
  const executeBatchDelete = async () => {
    setDeleteState(prev => ({
      ...prev,
      status: 'processing',
      processed: 0,
      offset: 0,
      errors: [],
      duration: 0
    }));
    const startTime = Date.now();
    const controller = new AbortController();
    setDeleteState(prev => ({
      ...prev,
      abortController: controller
    }));
    let hasMore = true;
    let offset = 0;
    try {
      while (hasMore && !controller.signal.aborted) {
        try {
          const path = mode === 'sync' ? '/gg-data/v1/sync/batch-delete' : '/gg-data/v1/vectors/batch-delete';
          const payload = mode === 'sync' ? {
            connection_name: connectionName,
            batch_size: batchSize,
            offset,
            limit: totalVectors
          } : {
            model_key: modelKey,
            connection_name: connectionName,
            batch_size: batchSize,
            offset,
            limit: totalVectors
          };
          const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
            path,
            method: 'POST',
            data: payload,
            signal: controller.signal
          });
          if (!response.success) {
            throw new Error(response.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Batch delete failed', 'gregius-data'));
          }

          // Update state with batch result
          setDeleteState(prev => ({
            ...prev,
            processed: response.total_deleted,
            offset: response.next_offset,
            duration: Date.now() - startTime,
            errors: response.errors && response.errors.length > 0 ? [...prev.errors, ...response.errors] : prev.errors
          }));
          hasMore = response.has_more;
          offset = response.next_offset;

          // Throttle to avoid hammering server
          if (hasMore) {
            await new Promise(resolve => setTimeout(resolve, 100));
          }
        } catch (error) {
          if (error.name === 'AbortError') {
            setDeleteState(prev => ({
              ...prev,
              status: 'stopped',
              duration: Date.now() - startTime
            }));
            return;
          }

          // Batch request error
          setDeleteState(prev => ({
            ...prev,
            status: 'error',
            errors: [...prev.errors, {
              error: error.message,
              batch: offset
            }],
            duration: Date.now() - startTime
          }));
          return;
        }
      }

      // Success: all batches complete
      if (!controller.signal.aborted) {
        setDeleteState(prev => ({
          ...prev,
          status: 'completed',
          duration: Date.now() - startTime
        }));
      }
    } catch (err) {
      setDeleteState(prev => ({
        ...prev,
        status: 'error',
        duration: Date.now() - startTime,
        errors: [...prev.errors, {
          error: err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Unexpected error', 'gregius-data')
        }]
      }));
    }
  };

  /**
   * Handle Stop button
   */
  const handleStop = () => {
    if (deleteState.abortController) {
      deleteState.abortController.abort();
    }
  };

  /**
   * Handle Close/Complete
   */
  const handleClose = () => {
    // Don't close while processing
    if (deleteState.status === 'processing') {
      return;
    }

    // Call success callback if completed
    if (deleteState.status === 'completed' && onSuccess) {
      onSuccess(deleteState.processed);
    }

    // Reset and close
    setDeleteState({
      status: 'idle',
      processed: 0,
      total: totalVectors,
      offset: 0,
      errors: [],
      duration: 0,
      abortController: null
    });
    onClose();
  };

  /**
   * Retry current batch
   */
  const handleRetry = () => {
    setDeleteState(prev => ({
      ...prev,
      status: 'idle',
      errors: [] // Clear errors for retry
    }));
    // Don't reset offset - retry from same position
    executeBatchDelete();
  };
  if (!isOpen) {
    return null;
  }
  const progressPercent = deleteState.total > 0 ? Math.min(100, Math.round(deleteState.processed / deleteState.total * 100)) : 0;
  const canClose = deleteState.status === 'idle' || deleteState.status === 'completed' || deleteState.status === 'error' || deleteState.status === 'stopped';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)(mode === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete Synced Content', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete Vectors - %s', 'gregius-data'), modelKey || ''),
    onRequestClose: handleClose,
    shouldCloseOnClickOutside: canClose,
    shouldCloseOnEsc: canClose,
    className: "gg-data-batch-delete-modal",
    children: [deleteState.status === 'idle' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        children: mode === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This will delete synced content from PostgreSQL tables (wp_posts_chunks, wp_posts_clean, wp_posts). This operation cannot be undone.', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This will delete all %d vectors for %s. This operation cannot be undone.', 'gregius-data'), deleteState.total, modelKey)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          gap: '12px',
          justifyContent: 'flex-start',
          marginTop: '20px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          isDestructive: true,
          onClick: executeBatchDelete,
          children: mode === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete Synced Content', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Delete Vectors', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "link",
          onClick: handleClose,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'gregius-data')
        })]
      })]
    }), deleteState.status === 'processing' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [deleteState.total > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Deleting %d of %d (%d%%)', 'gregius-data'), deleteState.processed, deleteState.total, progressPercent)
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Deleted %d records so far...', 'gregius-data'), deleteState.processed)
      }), deleteState.errors.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "warning",
        isDismissible: false,
        style: {
          marginBottom: '16px'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Batch %d had errors. Continuing...', 'gregius-data'), deleteState.errors[0].batch)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          gap: '8px',
          justifyContent: 'flex-start'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "secondary",
          isBusy: true,
          disabled: true,
          children: mode === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Deleting sync data...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Deleting...', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "link",
          onClick: handleStop,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Stop', 'gregius-data')
        })]
      })]
    }), deleteState.status === 'completed' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "success",
        isDismissible: false,
        children: mode === 'sync' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Successfully deleted %d sync records in %s seconds', 'gregius-data'), deleteState.processed, (deleteState.duration / 1000).toFixed(1)) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Successfully deleted %d vectors in %s seconds', 'gregius-data'), deleteState.processed, (deleteState.duration / 1000).toFixed(1))
      }), deleteState.errors.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "warning",
        isDismissible: false,
        style: {
          marginTop: '12px'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('%d batch errors occurred but operation completed', 'gregius-data'), deleteState.errors.length)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        style: {
          marginTop: '20px',
          display: 'flex',
          justifyContent: 'flex-end',
          gap: '8px'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          onClick: handleClose,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gregius-data')
        })
      })]
    }), deleteState.status === 'error' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "error",
        isDismissible: false,
        children: deleteState.errors[0]?.error || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('An error occurred during deletion', 'gregius-data')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        style: {
          marginTop: '12px',
          fontSize: '13px',
          color: '#757575'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Deleted %d of %d vectors before error.', 'gregius-data'), deleteState.processed, deleteState.total)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          marginTop: '20px',
          display: 'flex',
          justifyContent: 'flex-end',
          gap: '8px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "primary",
          onClick: handleRetry,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Retry', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "link",
          onClick: handleClose,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gregius-data')
        })]
      })]
    }), deleteState.status === 'stopped' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "warning",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Stopped at %d%%. %d vectors deleted.', 'gregius-data'), progressPercent, deleteState.processed)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        style: {
          marginTop: '20px',
          display: 'flex',
          justifyContent: 'flex-end',
          gap: '8px'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          variant: "link",
          onClick: handleClose,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'gregius-data')
        })
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (BatchDeleteModal);

/***/ },

/***/ "./src/scripts/dashboard/pages/ConnectionsPage.js"
/*!********************************************************!*\
  !*** ./src/scripts/dashboard/pages/ConnectionsPage.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _components_connections_ConnectionList__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/connections/ConnectionList */ "./src/scripts/dashboard/components/connections/ConnectionList.js");
/* harmony import */ var _components_connections_ConnectionForm__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/connections/ConnectionForm */ "./src/scripts/dashboard/components/connections/ConnectionForm.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * Connections Page Component
 *
 * Database connection management interface for PostgreSQL connections.
 * Provides CRUD operations, connection testing, and health monitoring.
 */







// Import connection-specific components



const ConnectionsPage = ({
  settings,
  isLoading,
  error,
  apiStatus
}) => {
  const [showCreateModal, setShowCreateModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editingConnection, setEditingConnection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [testingConnection, setTestingConnection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [testResult, setTestResult] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [crudLoading, setCrudLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [crudError, setCrudError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [crudSuccess, setCrudSuccess] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Use WordPress data stores
  const {
    connections,
    isLoadingConnections,
    connectionsError
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    connections: select("gg-data/connections").getConnections(),
    isLoadingConnections: select("gg-data/connections").isLoading(),
    connectionsError: select("gg-data/connections").getError()
  }), []);
  const {
    addConnection,
    updateConnectionData,
    removeConnection
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)("gg-data/connections");

  // Test connection and health check functions (using apiFetch directly)
  const testConnection = async connectionName => {
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/connections/${connectionName}/test`,
        method: "POST"
      });
      return response;
    } catch (error) {
      throw error;
    }
  };
  const getConnectionHealth = async connectionName => {
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/connections/${connectionName}/health`,
        method: "GET"
      });
      return response;
    } catch (error) {
      throw error;
    }
  };

  // Handle creating a new connection
  const handleCreateConnection = async connectionData => {
    setCrudLoading(true);
    setCrudError(null);
    setCrudSuccess(null);

    // Extract name from connectionData, rest goes into config
    const {
      name,
      ...configFields
    } = connectionData;

    // Sanitize config based on provider type
    const providerType = configFields.type || "postgresql";
    const sanitizedConfig = {
      type: providerType,
      connect_timeout: Number(configFields.connect_timeout) || 30,
      description: typeof configFields.description === "string" ? configFields.description : "",
      is_active: configFields.is_active === true || configFields.is_active === "true" || configFields.is_active === 1 || configFields.is_active === "1"
    };

    // Add provider-specific fields
    if (providerType === "postgrest") {
      sanitizedConfig.project_url = typeof configFields.project_url === "string" ? configFields.project_url : "";
      sanitizedConfig.publishable_key = typeof configFields.publishable_key === "string" ? configFields.publishable_key : "";
      sanitizedConfig.secret_key = typeof configFields.secret_key === "string" ? configFields.secret_key : "";
    } else {
      // PostgreSQL fields
      sanitizedConfig.host = typeof configFields.host === "string" ? configFields.host : "";
      sanitizedConfig.port = Number(configFields.port) || 5432;
      sanitizedConfig.database = typeof configFields.database === "string" ? configFields.database : "";
      sanitizedConfig.username = typeof configFields.username === "string" ? configFields.username : "";
      sanitizedConfig.password = typeof configFields.password === "string" ? configFields.password : "";
      sanitizedConfig.ssl_mode = typeof configFields.ssl_mode === "string" ? configFields.ssl_mode : "";
    }
    try {
      // Make API call directly in component
      const requestData = {
        name: name,
        config: sanitizedConfig
      };
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: "/gg-data/v1/connections",
        method: "POST",
        data: requestData
      });

      // Update store with new connection (store expects flat config object)
      if (response.success) {
        addConnection(name, sanitizedConfig);
      }
      setShowCreateModal(false);
      setCrudSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Connection created successfully.", "gregius-data"));
    } catch (error) {
      setCrudError(error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Failed to create connection", "gregius-data"));
    } finally {
      setCrudLoading(false);
    }
  };

  // Handle editing an existing connection
  const handleEditConnection = async (connectionName, connectionData) => {
    setCrudLoading(true);
    setCrudError(null);
    setCrudSuccess(null);
    // Sanitize config based on provider type
    // connectionData is already a flat object with all fields directly on it
    const providerType = connectionData.type || "postgresql";
    const sanitizedConfig = {
      type: providerType,
      connect_timeout: Number(connectionData.connect_timeout) || 30,
      description: typeof connectionData.description === "string" ? connectionData.description : "",
      is_active: connectionData.is_active === true || connectionData.is_active === "true" || connectionData.is_active === 1 || connectionData.is_active === "1"
    };

    // Add provider-specific fields
    if (providerType === "postgrest") {
      sanitizedConfig.project_url = typeof connectionData.project_url === "string" ? connectionData.project_url : "";
      if (connectionData.publishable_key && connectionData.publishable_key !== "***") {
        sanitizedConfig.publishable_key = connectionData.publishable_key;
      }
      if (connectionData.secret_key && connectionData.secret_key !== "***") {
        sanitizedConfig.secret_key = connectionData.secret_key;
      }
    } else {
      // PostgreSQL fields
      sanitizedConfig.host = typeof connectionData.host === "string" ? connectionData.host : "";
      sanitizedConfig.port = Number(connectionData.port) || 5432;
      sanitizedConfig.database = typeof connectionData.database === "string" ? connectionData.database : "";
      sanitizedConfig.username = typeof connectionData.username === "string" ? connectionData.username : "";
      if (connectionData.password && connectionData.password !== "***") {
        sanitizedConfig.password = connectionData.password;
      }
      sanitizedConfig.ssl_mode = typeof connectionData.ssl_mode === "string" ? connectionData.ssl_mode : "";
    }
    try {
      // Make API call directly in component - PUT endpoint expects only { config } in body
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/connections/${connectionName}`,
        method: "PUT",
        data: {
          config: sanitizedConfig
        }
      });

      // Update store with modified connection
      if (response.success) {
        updateConnectionData(connectionName, sanitizedConfig);
      }
      setEditingConnection(null);
      setCrudSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Connection updated successfully.", "gregius-data"));
    } catch (error) {
      setCrudError(error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Failed to update connection", "gregius-data"));
    } finally {
      setCrudLoading(false);
    }
  };

  // Handle deleting a connection
  const handleDeleteConnection = async connectionName => {
    if (window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Are you sure you want to delete this connection?", "gregius-data"))) {
      setCrudLoading(true);
      setCrudError(null);
      setCrudSuccess(null);
      try {
        // Make API call directly in component
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/gg-data/v1/connections/${connectionName}`,
          method: "DELETE"
        });

        // Update store by removing connection
        if (response.success) {
          removeConnection(connectionName);
        }
        setCrudSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Connection deleted successfully.", "gregius-data"));
      } catch (error) {
        setCrudError(error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Failed to delete connection", "gregius-data"));
      } finally {
        setCrudLoading(false);
      }
    }
  };

  // Handle testing a connection
  const handleTestConnection = async connectionName => {
    setTestingConnection(connectionName);
    try {
      const result = await testConnection(connectionName);
      setTestResult({
        connectionName,
        result
      });
    } catch (error) {
      setTestResult({
        connectionName,
        result: {
          success: false,
          message: error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Connection test failed", "gregius-data")
        }
      });
    } finally {
      setTestingConnection(null);
    }
  };

  // Handle starting edit mode
  const handleStartEdit = connection => {
    setEditingConnection(connection);
  };

  // Handle canceling edit mode
  const handleCancelEdit = () => {
    setEditingConnection(null);
  };

  // Show loading state
  if (isLoading || isLoadingConnections) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gg-data-page",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Card, {
        isRounded: false,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardBody, {
          style: {
            textAlign: "center",
            padding: "40px"
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            style: {
              marginTop: "16px"
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Loading connections...", "gregius-data")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, {})]
        })
      }), crudLoading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, {
        style: {
          marginTop: "16px"
        }
      }), crudError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "error",
        isDismissible: true,
        onDismiss: () => setCrudError(null),
        children: crudError
      }), crudSuccess && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "success",
        isDismissible: true,
        onDismiss: () => setCrudSuccess(null),
        children: crudSuccess
      })]
    });
  }

  // Show error state
  if (connectionsError) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gg-data-page",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "error",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Failed to load connections: ", "gregius-data") + connectionsError.message
      }), crudError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "error",
        isDismissible: true,
        onDismiss: () => setCrudError(null),
        children: crudError
      }), crudSuccess && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "success",
        isDismissible: true,
        onDismiss: () => setCrudSuccess(null),
        children: crudSuccess
      })]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "gg-data-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "gg-data-page",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        style: {
          display: "flex",
          alignItems: "center",
          flexWrap: "wrap",
          justifyContent: "space-between",
          gap: 16,
          padding: "2rem 1.5rem 0",
          borderTop: "1px solid rgba(0, 0, 0, 0.1)"
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          style: {
            flex: "1"
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalHeading, {
            level: 2,
            style: {
              margin: 0
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Connections", "gregius-data")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            style: {
              margin: 0
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Manage database connections for content synchronization.", "gregius-data")
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          variant: "primary",
          onClick: () => setShowCreateModal(true),
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Add Connection", "gregius-data")
        })]
      }), crudLoading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, {
        style: {
          marginBottom: "16px"
        }
      }), crudError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "error",
        isDismissible: true,
        onDismiss: () => setCrudError(null),
        children: crudError
      }), crudSuccess && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "success",
        isDismissible: true,
        onDismiss: () => setCrudSuccess(null),
        children: crudSuccess
      }), apiStatus && !apiStatus.success && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        status: "warning",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("REST API connection issues detected. Some features may not work properly.", "gregius-data")
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_components_connections_ConnectionList__WEBPACK_IMPORTED_MODULE_5__["default"], {
        connections: connections || {},
        onEdit: handleStartEdit,
        onDelete: handleDeleteConnection,
        onTest: handleTestConnection,
        onAdd: () => setShowCreateModal(true),
        testingConnection: testingConnection,
        testResults: testResult,
        onDismissTestResult: () => setTestResult(null)
      })]
    }), showCreateModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Modal, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Add New Connection", "gregius-data"),
      onRequestClose: () => setShowCreateModal(false),
      className: "gg-data-connection-modal",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_components_connections_ConnectionForm__WEBPACK_IMPORTED_MODULE_6__["default"], {
        onSubmit: handleCreateConnection,
        onCancel: () => setShowCreateModal(false),
        submitLabel: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Create Connection", "gregius-data")
      })
    }), editingConnection && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Modal, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Edit Connection", "gregius-data"),
      onRequestClose: handleCancelEdit,
      className: "gg-data-connection-modal",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_components_connections_ConnectionForm__WEBPACK_IMPORTED_MODULE_6__["default"], {
        initialData: editingConnection,
        onSubmit: data => handleEditConnection(editingConnection.name, data),
        onCancel: handleCancelEdit,
        submitLabel: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)("Update Connection", "gregius-data"),
        isEdit: true
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectionsPage);

/***/ },

/***/ "./src/scripts/dashboard/pages/LogsPage.js"
/*!*************************************************!*\
  !*** ./src/scripts/dashboard/pages/LogsPage.js ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var _stores_logs__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../stores/logs */ "./src/scripts/dashboard/stores/logs/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * Logs Page Component
 *
 * Dashboard page for viewing and managing plugin logs and interactions.
 */








// Import the logs store


// Level badge class mappings

const LEVEL_BADGE_CLASSES = {
  debug: 'is-info',
  // Default badge style
  info: 'is-success',
  // Green
  warning: 'is-warning',
  // Yellow/Orange
  error: 'is-error',
  // Red
  critical: 'is-error' // Red (critical uses same as error)
};
const LogsPage = () => {
  // Local state for filters
  const [filterLevel, setFilterLevel] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [filterComponent, setFilterComponent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [filterDateFrom, setFilterDateFrom] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [filterDateTo, setFilterDateTo] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [expandedLogId, setExpandedLogId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Get data from store
  const {
    logs,
    pagination,
    stats,
    settings,
    isLoading,
    error,
    autoRefresh
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    logs: select('gg-data/logs').getLogs(),
    pagination: select('gg-data/logs').getPagination(),
    stats: select('gg-data/logs').getStats(),
    settings: select('gg-data/logs').getSettings(),
    isLoading: select('gg-data/logs').isLoading(),
    error: select('gg-data/logs').getError(),
    autoRefresh: select('gg-data/logs').getAutoRefresh()
  }), []);

  // Get connections for filter dropdown
  const {
    connections
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    connections: select('gg-data/connections').getConnectionsList()
  }), []);
  const [filterConnection, setFilterConnection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');

  // Store actions
  const {
    fetchLogs,
    fetchStats,
    fetchSettings,
    setAutoRefresh
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)('gg-data/logs');

  // Build current filters object
  const getCurrentFilters = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => ({
    page: pagination?.page || 1,
    per_page: pagination?.perPage || 50,
    level: filterLevel || undefined,
    component: filterComponent || undefined,
    connection_id: filterConnection || undefined,
    date_from: filterDateFrom || undefined,
    date_to: filterDateTo || undefined
  }), [pagination, filterLevel, filterComponent, filterConnection, filterDateFrom, filterDateTo]);

  // Initial load
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchLogs();
    fetchStats();
    fetchSettings();
  }, []);

  // Auto-refresh effect
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    let interval;
    if (autoRefresh) {
      interval = setInterval(() => {
        fetchLogs(getCurrentFilters());
        fetchStats(); // Update level counts in dropdown
      }, 5000);
    }
    return () => {
      if (interval) {
        clearInterval(interval);
      }
    };
  }, [autoRefresh, fetchLogs, getCurrentFilters]);

  // Effect to fetch when filters change (instant filtering)
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchLogs({
      ...getCurrentFilters(),
      page: 1 // Reset to first page when filtering
    });
    fetchStats();
  }, [filterLevel, filterComponent, filterConnection, filterDateFrom, filterDateTo]);

  // Pagination
  const handlePageChange = newPage => {
    fetchLogs({
      ...getCurrentFilters(),
      page: newPage
    });
  };

  // Export handlers
  const handleExportCsv = () => {
    const params = new URLSearchParams();
    params.append('format', 'csv');
    if (filterLevel) params.append('level', filterLevel);
    if (filterComponent) params.append('component', filterComponent);
    if (filterConnection) params.append('connection_id', filterConnection);
    if (filterDateFrom) params.append('date_from', filterDateFrom);
    if (filterDateTo) params.append('date_to', filterDateTo);
    const restUrl = window.wpApiSettings?.root || '/wp-json/';
    const exportUrl = `${restUrl}gg-data/v1/logs/export?${params.toString()}&_wpnonce=${window.wpApiSettings?.nonce || ''}`;
    window.open(exportUrl, '_blank');
  };
  const handleExportJson = () => {
    const params = new URLSearchParams();
    params.append('format', 'json');
    if (filterLevel) params.append('level', filterLevel);
    if (filterComponent) params.append('component', filterComponent);
    if (filterConnection) params.append('connection_id', filterConnection);
    if (filterDateFrom) params.append('date_from', filterDateFrom);
    if (filterDateTo) params.append('date_to', filterDateTo);
    const restUrl = window.wpApiSettings?.root || '/wp-json/';
    const exportUrl = `${restUrl}gg-data/v1/logs/export?${params.toString()}&_wpnonce=${window.wpApiSettings?.nonce || ''}`;
    window.open(exportUrl, '_blank');
  };

  // Level options for select with counts from stats
  const getLevelCount = level => {
    if (!stats?.by_level) return 0;
    return stats.by_level[level] || 0;
  };
  const levelOptions = [{
    label: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All Levels', 'gregius-data')} (${stats?.total || 0})`,
    value: ''
  }, {
    label: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Debug', 'gregius-data')} (${getLevelCount('debug')})`,
    value: 'debug'
  }, {
    label: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Info', 'gregius-data')} (${getLevelCount('info')})`,
    value: 'info'
  }, {
    label: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Warning', 'gregius-data')} (${getLevelCount('warning')})`,
    value: 'warning'
  }, {
    label: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Error', 'gregius-data')} (${getLevelCount('error')})`,
    value: 'error'
  }, {
    label: `${(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Critical', 'gregius-data')} (${getLevelCount('critical')})`,
    value: 'critical'
  }];

  // Component options for select
  const componentOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All Components', 'gregius-data'),
    value: ''
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('RAG', 'gregius-data'),
    value: 'rag'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search', 'gregius-data'),
    value: 'search'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync', 'gregius-data'),
    value: 'sync'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Vectors', 'gregius-data'),
    value: 'vectors'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Connection', 'gregius-data'),
    value: 'connection'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Model', 'gregius-data'),
    value: 'model'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cron', 'gregius-data'),
    value: 'cron'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('System', 'gregius-data'),
    value: 'system'
  }];

  // Connection options for select
  const connectionOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All Connections', 'gregius-data'),
    value: ''
  }, ...(connections || []).map(conn => ({
    label: conn.name,
    value: conn.name
  }))];

  // Format timestamp
  const formatTime = timestamp => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };
  const formatDate = timestamp => {
    const date = new Date(timestamp);
    return date.toLocaleDateString();
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "gg-data-page gg-data-logs-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      style: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 16,
        padding: '2rem 1.5rem 0',
        borderTop: '1px solid rgba(0, 0, 0, 0.1)'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalHeading, {
        level: 2,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Logs', 'gregius-data')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
        gap: 3,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.ToggleControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Auto-refresh', 'gregius-data'),
            checked: autoRefresh,
            onChange: setAutoRefresh
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            variant: "secondary",
            onClick: () => {
              fetchLogs(getCurrentFilters());
              fetchStats();
            },
            disabled: isLoading,
            isBusy: isLoading,
            children: isLoading ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Refreshing...', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Refresh', 'gregius-data')
          })
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: '1.5rem',
        padding: '1.5rem'
      },
      children: [error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(Notice, {
        status: "error",
        isDismissible: false,
        children: error
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Card, {
        isRounded: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.CardHeader, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalHeading, {
            level: 4,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Filters', 'gregius-data')
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.CardBody, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
            gap: 4,
            wrap: true,
            align: "flex-end",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.SelectControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Level', 'gregius-data'),
                value: filterLevel,
                options: levelOptions,
                onChange: setFilterLevel,
                __nextHasNoMarginBottom: true
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.SelectControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Component', 'gregius-data'),
                value: filterComponent,
                options: componentOptions,
                onChange: setFilterComponent,
                __nextHasNoMarginBottom: true
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.SelectControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Connection', 'gregius-data'),
                value: filterConnection,
                options: connectionOptions,
                onChange: setFilterConnection,
                __nextHasNoMarginBottom: true
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.TextControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('From Date', 'gregius-data'),
                type: "date",
                value: filterDateFrom,
                onChange: setFilterDateFrom,
                __nextHasNoMarginBottom: true
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.FlexItem, {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.TextControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('To Date', 'gregius-data'),
                type: "date",
                value: filterDateTo,
                onChange: setFilterDateTo,
                __nextHasNoMarginBottom: true
              })
            })]
          })
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Card, {
        isRounded: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.CardHeader, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
            justify: "space-between",
            align: "center",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalHeading, {
              level: 4,
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Log Entries', 'gregius-data'), pagination && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalText, {
                as: "span",
                style: {
                  fontWeight: 'normal',
                  marginLeft: 8
                },
                children: ["(", pagination.totalItems, " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('total', 'gregius-data'), ")"]
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.DropdownMenu, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Log actions', 'gregius-data'),
              controls: [{
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export CSV', 'gregius-data'),
                onClick: handleExportCsv
              }, {
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export JSON', 'gregius-data'),
                onClick: handleExportJson
              }]
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.CardBody, {
          children: isLoading && !logs?.length ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
            justify: "center",
            style: {
              padding: 40
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Spinner, {})
          }) : /* Empty state */!logs?.length ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
            justify: "center",
            style: {
              padding: 40
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalText, {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No logs found.', 'gregius-data')
            })
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("table", {
              style: {
                margin: 0
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("thead", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("tr", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                    style: {
                      width: 60
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Level', 'gregius-data')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                    style: {
                      width: 100
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Component', 'gregius-data')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                    style: {
                      width: 150
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Time', 'gregius-data')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Message', 'gregius-data')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                    style: {
                      width: 120
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Connection', 'gregius-data')
                  })]
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("tbody", {
                children: logs.map(item => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("tr", {
                    onClick: () => setExpandedLogId(expandedLogId === item.id ? null : item.id),
                    style: {
                      cursor: item.context ? 'pointer' : 'default'
                    },
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                        className: `components-badge ${LEVEL_BADGE_CLASSES[item.level] || ''}`,
                        style: {
                          textTransform: 'uppercase'
                        },
                        children: item.level
                      })
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                        className: "components-badge is-info",
                        children: item.component
                      })
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalText, {
                        size: "small",
                        children: [formatDate(item.logged_at), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("br", {}), formatTime(item.logged_at)]
                      })
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("td", {
                      children: [item.message, item.context && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                        style: {
                          marginLeft: 8,
                          color: '#666'
                        },
                        children: expandedLogId === item.id ? '▼' : '▶'
                      })]
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalText, {
                        size: "small",
                        children: item.connection_id || '-'
                      })
                    })]
                  }, item.id), expandedLogId === item.id && item.context && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("tr", {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                      colSpan: 5,
                      style: {
                        backgroundColor: '#f6f7f7',
                        padding: 16
                      },
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("pre", {
                        style: {
                          margin: 0,
                          whiteSpace: 'pre-wrap',
                          fontSize: 12
                        },
                        children: JSON.stringify(item.context, null, 2)
                      })
                    })
                  }, `${item.id}-context`)]
                }))
              })]
            }), pagination && pagination.totalPages > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
              justify: "space-between",
              align: "center",
              style: {
                padding: 16,
                borderTop: '1px solid #ddd'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalText, {
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Showing', 'gregius-data'), " ", (pagination.page - 1) * pagination.perPage + 1, "-", Math.min(pagination.page * pagination.perPage, pagination.totalItems), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('of', 'gregius-data'), " ", pagination.totalItems]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Flex, {
                gap: 2,
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
                  variant: "secondary",
                  size: "small",
                  disabled: pagination.page <= 1,
                  onClick: () => handlePageChange(pagination.page - 1),
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('← Previous', 'gregius-data')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalText, {
                  style: {
                    padding: '6px 12px'
                  },
                  children: [pagination.page, " / ", pagination.totalPages]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
                  variant: "secondary",
                  size: "small",
                  disabled: pagination.page >= pagination.totalPages,
                  onClick: () => handlePageChange(pagination.page + 1),
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Next →', 'gregius-data')
                })]
              })]
            })]
          })
        })]
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (LogsPage);

/***/ },

/***/ "./src/scripts/dashboard/pages/ModelsPage.js"
/*!***************************************************!*\
  !*** ./src/scripts/dashboard/pages/ModelsPage.js ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Models Page Component
 *
 * Manages AI Model configurations.
 *
 * @since 2.1.0
 */







const ModelsPage = () => {
  const [models, setModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [providers, setProviders] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [showModal, setShowModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [isTesting, setIsTesting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [testResult, setTestResult] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // Model dimensions mapping
  const MODEL_DIMENSIONS = {
    // OpenAI (Ada-002 excluded - legacy model)
    "text-embedding-3-small": 1536,
    "text-embedding-3-large": 3072,
    // Google Gemini
    "gemini-embedding-2": 3072,
    // Voyage AI
    "voyage-4": 1024,
    // Cohere
    "embed-v4.0": 1536,
    // Internal
    "hashingtf-murmur3-1024": 1024
  };

  // Model context window limits (tokens)
  const MODEL_CONTEXT_LIMITS = {
    // OpenAI
    "gpt-3.5-turbo": 16385,
    "gpt-4": 8192,
    "gpt-4-turbo": 128000,
    "gpt-4o": 128000,
    "gpt-4o-mini": 128000,
    // DeepSeek
    "deepseek-chat": 64000,
    "deepseek-coder": 64000,
    "deepseek-reasoner": 64000,
    // Anthropic Claude
    "claude-3-5-sonnet-20241022": 200000,
    "claude-3-5-haiku-20241022": 200000,
    "claude-3-opus-20240229": 200000,
    "claude-3-sonnet-20240229": 200000,
    "claude-3-haiku-20240307": 200000,
    // Google Gemini
    "gemini-2.5-flash": 1048576,
    "gemini-2.5-pro": 1048576,
    "gemini-2.0-flash": 1048576,
    "gemini-2.0-flash-lite": 1048576,
    "gemini-1.5-pro": 2097152,
    "gemini-1.5-flash": 1048576,
    "gemini-1.5-flash-8b": 1048576
  };

  // Form state
  // Native max output tokens per model (provider hard ceilings)
  const MODEL_MAX_TOKENS = {
    // OpenAI
    "gpt-3.5-turbo": 4096,
    "gpt-4": 8192,
    "gpt-4-turbo": 4096,
    "gpt-4o": 16384,
    "gpt-4o-mini": 16384,
    // DeepSeek
    "deepseek-chat": 8192,
    "deepseek-coder": 8192,
    "deepseek-reasoner": 8192,
    // Anthropic Claude
    "claude-3-5-sonnet-20241022": 8192,
    "claude-3-5-haiku-20241022": 8192,
    "claude-3-opus-20240229": 4096,
    "claude-3-sonnet-20240229": 4096,
    "claude-3-haiku-20240307": 4096,
    // Google Gemini
    "gemini-2.5-flash": 8192,
    "gemini-2.5-pro": 8192,
    "gemini-2.0-flash": 8192,
    "gemini-2.0-flash-lite": 8192,
    "gemini-1.5-pro": 8192,
    "gemini-1.5-flash": 8192,
    "gemini-1.5-flash-8b": 8192
  };

  // Form state
  const [editingId, setEditingId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    id: "",
    type: "llm",
    provider: "openai",
    api_key: "",
    model_name: "",
    max_tokens: "",
    context_window: "",
    dimensions: 1536
  });
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchData();
  }, []);
  const fetchData = async () => {
    try {
      setIsLoading(true);
      const [modelsData, providersData] = await Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: "/gg-data/v1/models"
      }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: "/gg-data/v1/models/providers"
      })]);

      // Convert object to array if needed (PHP associative array comes as object)
      const modelsArray = Array.isArray(modelsData.data) ? modelsData.data : Object.values(modelsData.data);
      setModels(modelsArray);
      setProviders(providersData.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };
  const getProviderModels = () => {
    const provider = providers.find(p => p.id === formData.provider);
    if (!provider) {
      return [];
    }

    // Select the correct list based on type
    let modelsList;
    if (formData.type === "embeddings") {
      modelsList = provider.embedding_models;
    } else if (formData.type === "rerank") {
      modelsList = provider.rerank_models;
    } else {
      modelsList = provider.llm_models;
    }
    if (!modelsList) {
      return [];
    }

    // Handle both array (list of strings) and object (slug => name) formats
    if (Array.isArray(modelsList)) {
      return modelsList.map(m => ({
        label: m,
        value: m
      }));
    }
    return Object.entries(modelsList).map(([value, label]) => ({
      label,
      value
    }));
  };

  // Get dimensions for a specific model
  const getDimensionsForModel = modelName => {
    return MODEL_DIMENSIONS[modelName] || 1536; // Default to 1536 if not found
  };

  // Check if provider requires API key
  const isApiKeyRequired = () => {
    return formData.provider !== "internal";
  };

  // Get default context window for a model (full model limit)
  const getDefaultContextWindow = modelName => {
    if (!modelName) {
      return "";
    }
    const limit = MODEL_CONTEXT_LIMITS[modelName] || 128000;
    return limit;
  };

  // Get default max tokens for a model (native provider ceiling)
  const getDefaultMaxTokens = modelName => {
    if (!modelName) {
      return "";
    }
    return MODEL_MAX_TOKENS[modelName] || 8192;
  };

  // Handle model name change and auto-populate dimensions/context_window
  const handleModelNameChange = modelName => {
    let newDimensions = formData.dimensions;
    let newContextWindow = formData.context_window;
    let newMaxTokens = formData.max_tokens;
    if (formData.type === "embeddings") {
      // If no model selected (empty string), clear dimensions
      newDimensions = modelName ? getDimensionsForModel(modelName) : "";
    } else {
      // For LLM models, auto-set context_window and max_tokens based on model
      newContextWindow = getDefaultContextWindow(modelName);
      newMaxTokens = getDefaultMaxTokens(modelName);
    }
    setFormData({
      ...formData,
      model_name: modelName,
      dimensions: newDimensions,
      context_window: newContextWindow,
      max_tokens: newMaxTokens
    });
  };

  /**
   * Get providers that support a given model type.
   *
   * @param {string} type - Model type (llm, embeddings, rerank).
   * @return {Array} Filtered providers.
   */
  const getProvidersForType = type => {
    return providers.filter(p => {
      if (type === "embeddings") {
        return p.capabilities && p.capabilities.includes("embeddings");
      }
      if (type === "rerank") {
        return p.capabilities && p.capabilities.includes("rerank");
      }
      return !p.capabilities || p.capabilities.includes("llm");
    });
  };

  /**
   * Handle model type change.
   * Auto-switches provider if current one doesn't support the new type.
   *
   * @param {string} newType - The new model type.
   */
  const handleTypeChange = newType => {
    const validProviders = getProvidersForType(newType);
    const currentProviderValid = validProviders.some(p => p.id === formData.provider);

    // If current provider doesn't support the new type, switch to first valid provider.
    const newProvider = currentProviderValid ? formData.provider : validProviders[0]?.id || "openai";
    setFormData({
      ...formData,
      type: newType,
      provider: newProvider,
      model_name: "",
      // Clear model selection.
      dimensions: "",
      api_key: newProvider === "internal" ? "" : formData.api_key
    });
  };

  // Handle provider change
  const handleProviderChange = provider => {
    setFormData({
      ...formData,
      provider: provider,
      // Clear API key if switching to internal provider
      api_key: provider === "internal" ? "" : formData.api_key,
      // Clear model name, dimensions and tokens when provider changes
      model_name: "",
      dimensions: "",
      context_window: "",
      max_tokens: ""
    });
  };
  const handleTest = async () => {
    setIsTesting(true);
    setTestResult(null);
    try {
      const config = {
        provider: formData.provider,
        api_key: formData.api_key
      };

      // If editing and key is masked, pass ID so backend can use stored key
      const requestData = {
        config: config
      };
      if (editingId) {
        requestData.id = editingId;
      }
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: "/gg-data/v1/models/test",
        method: "POST",
        data: requestData
      });
      setTestResult({
        success: true,
        message: response.message
      });
    } catch (err) {
      setTestResult({
        success: false,
        message: err.message
      });
    } finally {
      setIsTesting(false);
    }
  };
  const handleSave = async () => {
    try {
      setIsSaving(true);
      setError(null);

      // Encode model ID for URL - explicitly encode dots to prevent web server
      // from interpreting them as file extensions (e.g., gpt-3.5-turbo).
      const encodedId = editingId ? encodeURIComponent(editingId).replace(/\./g, "%2E") : null;
      const path = encodedId ? `/gg-data/v1/models/${encodedId}` : "/gg-data/v1/models";
      const method = editingId ? "PUT" : "POST";
      const config = {
        provider: formData.provider,
        provider_model_id: formData.model_name,
        model_type: formData.type,
        model_name: formData.model_name
      };
      if (formData.type === "embeddings") {
        config.dimensions = parseInt(formData.dimensions);
      } else {
        const parsedMaxTokens = parseInt(formData.max_tokens, 10);
        const parsedContextWindow = parseInt(formData.context_window, 10);
        if (Number.isFinite(parsedMaxTokens)) {
          config.max_tokens = parsedMaxTokens;
        }
        if (Number.isFinite(parsedContextWindow)) {
          config.context_window = parsedContextWindow;
        }
      }

      // Only send API key if it's provided and not the masked value
      if (formData.api_key && formData.api_key !== "***") {
        config.api_key = formData.api_key;
      }

      // Build request data - only include id when editing
      const requestData = {
        config: config
      };
      if (editingId) {
        requestData.id = editingId;
      }
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path,
        method,
        data: requestData
      });
      setShowModal(false);
      fetchData();
      resetForm();
    } catch (err) {
      setError(err.message);
    } finally {
      setIsSaving(false);
    }
  };
  const handleDelete = async id => {
    if (!confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Are you sure you want to delete this model?", "gregius-data"))) {
      return;
    }
    try {
      setIsLoading(true);
      // Encode dots to prevent web server from treating them as file extensions.
      const encodedId = encodeURIComponent(id).replace(/\./g, "%2E");
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/models/${encodedId}`,
        method: "DELETE"
      });
      fetchData();
    } catch (err) {
      setError(err.message);
      setIsLoading(false);
    }
  };
  const handleEdit = model => {
    const modelId = model.id || model.model_key || "";
    setEditingId(modelId);

    // Handle both old flat structure and new nested config structure
    const provider = model.provider || model.config && model.config.provider || "openai";
    const api_key = model.api_key || model.config && model.config.api_key || "";
    const model_name = model.model_name || model.config && model.config.model_name || "";
    const max_tokens = model.max_tokens || model.config && model.config.max_tokens || getDefaultMaxTokens(model_name);
    // Use model-specific default: model's full context limit
    const modelLimit = MODEL_CONTEXT_LIMITS[model_name] || 128000;
    const defaultContextWindow = modelLimit;
    const context_window = model.context_window || model.config && model.config.context_window || defaultContextWindow;
    // Determine type: rerank, embeddings, or llm
    let type = "llm";
    if (model.model_type === "rerank") {
      type = "rerank";
    } else if (model.model_type === "embeddings" || model.dimensions) {
      type = "embeddings";
    }
    const dimensions = model.dimensions || model.config && model.config.dimensions || 1536;
    setFormData({
      id: modelId.replace("model_", ""),
      // Strip prefix for display
      type: type,
      provider: provider,
      api_key: api_key,
      model_name: model_name,
      max_tokens: max_tokens,
      context_window: context_window,
      dimensions: dimensions
    });
    setShowModal(true);
  };
  const resetForm = () => {
    setEditingId(null);
    setTestResult(null);
    setFormData({
      id: "",
      type: "llm",
      provider: "openai",
      api_key: "",
      model_name: "",
      max_tokens: "",
      context_window: "",
      dimensions: ""
    });
  };

  /**
   * Get maximum allowed context window for selected model.
   *
   * @return {number} Maximum context window tokens.
   */
  const getMaxContextWindow = () => {
    if (!formData.model_name) {
      return null;
    }
    return MODEL_CONTEXT_LIMITS[formData.model_name] || 128000;
  };
  const getProviderName = id => {
    const provider = providers.find(p => p.id === id);
    return provider ? provider.name : id;
  };
  const handleResetUsage = async id => {
    if (!confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Are you sure you want to reset usage stats for this model?", "gregius-data"))) {
      return;
    }
    try {
      setIsLoading(true);
      // Encode dots to prevent web server from treating them as file extensions.
      const encodedId = encodeURIComponent(id).replace(/\./g, "%2E");
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: `/gg-data/v1/models/${encodedId}/reset-usage`,
        method: "POST"
      });
      fetchData();
    } catch (err) {
      setError(err.message);
      setIsLoading(false);
    }
  };
  if (isLoading && !models.length) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "gg-data-page",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          style: {
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            marginBottom: "3rem",
            padding: "2rem 1.5rem",
            borderTop: "1px solid rgba(0, 0, 0, 0.1)"
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
            level: 2,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("AI Models", "gregius-data")
          })
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        style: {
          marginTop: "16px"
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Loading models...", "gregius-data")
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {}), ";"]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "gg-data-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      style: {
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
        padding: "2rem 1.5rem 0",
        borderTop: "1px solid rgba(0, 0, 0, 0.1)"
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        style: {
          display: "flex",
          flexDirection: "column"
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
          level: 2,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("AI Models", "gregius-data")
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Manage AI models.", "gregius-data")
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        style: {
          display: "flex",
          gap: "10px",
          alignItems: "center"
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: () => {
            resetForm();
            setShowModal(true);
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Add New Model", "gregius-data")
        })
      })]
    }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
      status: "error",
      isDismissible: true,
      onRemove: () => setError(null),
      children: error
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "gg-data-model-cards",
      style: {
        display: "grid",
        gridTemplateColumns: "repeat(auto-fill, minmax(400px, 1fr))",
        gap: "20px"
      },
      children: models.map(model => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
        isRounded: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardHeader, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
            style: {
              display: "flex",
              flexWrap: "wrap",
              gap: "1rem",
              justifyContent: "space-between",
              alignItems: "flex-start",
              width: "100%"
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
              style: {
                display: "flex",
                alignItems: "center",
                gap: "12px"
              },
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
                level: 3,
                style: {
                  margin: 0
                },
                children: model.id || model.model_key
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DropdownMenu, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Model actions", "gregius-data"),
              controls: [{
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit Model", "gregius-data"),
                onClick: () => handleEdit(model)
              }, {
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Reset Usage", "gregius-data"),
                onClick: () => handleResetUsage(model.id)
              }, {
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Delete Model", "gregius-data"),
                onClick: () => handleDelete(model.id),
                className: "has-text-color has-vivid-red-color"
              }]
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
            columns: 2,
            gap: 4,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Provider:", "gregius-data")
              }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: getProviderName(model.provider)
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Max Tokens:", "gregius-data")
              }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: model.max_tokens || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("N/A", "gregius-data")
              })]
            }), model.context_window && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Context Window:", "gregius-data")
              }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: model.context_window.toLocaleString()
              })]
            })]
          }), model.usage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("hr", {
              style: {
                margin: "16px 0"
              }
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalGrid, {
              columns: 2,
              gap: 4,
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Total Tokens:", "gregius-data")
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                  className: "components-badge is-info",
                  children: model.usage.total_tokens.toLocaleString()
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Total Queries:", "gregius-data")
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                  className: "components-badge is-info",
                  children: model.usage.total_queries.toLocaleString()
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Last Used:", "gregius-data")
                }), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                  className: "components-badge is-info",
                  children: model.usage.last_used_at || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Never", "gregius-data")
                })]
              })]
            })]
          })]
        })]
      }, model.id || model.model_key))
    }), models.length === 0 && !isLoading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Card, {
      isRounded: false,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CardBody, {
        style: {
          textAlign: "center",
          padding: "60px 40px"
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          style: {
            color: "#646970",
            marginBottom: "24px"
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Add your first AI Model to get started.", "gregius-data")
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "secondary",
          onClick: () => {
            resetForm();
            setShowModal(true);
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Add Your First AI Model", "gregius-data")
        })]
      })
    }), showModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: editingId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit Model", "gregius-data") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Add New Model", "gregius-data"),
      onRequestClose: () => setShowModal(false),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        style: {
          display: "flex",
          flexDirection: "column",
          gap: "16px"
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Model Type", "gregius-data"),
          value: formData.type,
          options: [{
            label: "LLM (Chat)",
            value: "llm"
          }, {
            label: "Embeddings (Vector)",
            value: "embeddings"
          }, {
            label: "Rerank",
            value: "rerank"
          }],
          onChange: handleTypeChange,
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Provider", "gregius-data"),
          value: formData.provider,
          options: getProvidersForType(formData.type).map(p => ({
            label: p.name,
            value: p.id
          })),
          onChange: handleProviderChange,
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("API Key", "gregius-data"),
          value: formData.api_key,
          type: "password",
          onChange: val => setFormData({
            ...formData,
            api_key: val
          }),
          help: !isApiKeyRequired() ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("No API key required for internal models", "gregius-data") : editingId ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Leave blank to keep existing key", "gregius-data") : "",
          disabled: !isApiKeyRequired(),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), getProviderModels().length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Model Name", "gregius-data"),
          value: formData.model_name,
          options: [{
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Select a model", "gregius-data"),
            value: ""
          }, ...getProviderModels()],
          onChange: handleModelNameChange,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Select the AI model to use.", "gregius-data"),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Model Name", "gregius-data"),
          value: formData.model_name,
          onChange: handleModelNameChange,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("e.g., gpt-4o, gpt-3.5-turbo", "gregius-data"),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), formData.type === "llm" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Max Tokens (Response)", "gregius-data"),
            value: formData.max_tokens,
            type: "number",
            onChange: val => setFormData({
              ...formData,
              max_tokens: val
            }),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Maximum tokens for the AI response output.", "gregius-data"),
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Context Window (Input)", "gregius-data"),
            value: formData.context_window,
            type: "number",
            onChange: val => {
              const maxAllowed = getMaxContextWindow();
              if (val === "") {
                setFormData({
                  ...formData,
                  context_window: ""
                });
                return;
              }
              const parsedVal = parseInt(val, 10) || 0;
              const newVal = maxAllowed ? Math.min(parsedVal, maxAllowed) : parsedVal;
              setFormData({
                ...formData,
                context_window: newVal
              });
            },
            help: getMaxContextWindow() ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Maximum tokens for RAG context. Model limit: %s tokens. System prompts and response tokens are reserved automatically at runtime.", "gregius-data"), getMaxContextWindow().toLocaleString()) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Select a model to see its context window limit.", "gregius-data"),
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true
          })]
        }), formData.type === "embeddings" && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Dimensions", "gregius-data"),
          value: formData.dimensions,
          type: "number",
          onChange: val => setFormData({
            ...formData,
            dimensions: val
          }),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Automatically set based on model. OpenAI small: 1536, OpenAI large: 3072", "gregius-data"),
          disabled: true,
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), " ", testResult && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: testResult.success ? "success" : "error",
          isDismissible: false,
          style: {
            margin: "0"
          },
          children: testResult.message
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            display: "flex",
            gap: "1rem",
            marginTop: "1rem",
            alignItems: "center"
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            onClick: handleSave,
            isBusy: isSaving,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Save Model", "gregius-data")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            onClick: handleTest,
            isBusy: isTesting,
            disabled: !isApiKeyRequired(),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Test Connection", "gregius-data")
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "tertiary",
            onClick: () => setShowModal(false),
            style: {
              marginLeft: "auto"
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Cancel", "gregius-data")
          })]
        })]
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ModelsPage);

/***/ },

/***/ "./src/scripts/dashboard/pages/PromptsPage.js"
/*!****************************************************!*\
  !*** ./src/scripts/dashboard/pages/PromptsPage.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Prompts Page Component
 *
 * Admin UI for managing reusable prompts used by the RAG pipeline.
 */







const DEFAULT_FORM = {
  title: '',
  prompt_type: 'system',
  status: 'draft',
  content: '',
  notes: ''
};
const promptStatusOptions = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Draft', 'gregius-data'),
  value: 'draft'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Published', 'gregius-data'),
  value: 'published'
}];
const promptTypeOptions = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('System', 'gregius-data'),
  value: 'system'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Security', 'gregius-data'),
  value: 'security'
}];
const PromptsPage = () => {
  const [prompts, setPrompts] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [isSubmitting, setIsSubmitting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [success, setSuccess] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [isModalOpen, setIsModalOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editingPrompt, setEditingPrompt] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [form, setForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(DEFAULT_FORM);
  const loadPrompts = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/gg-data/v1/prompts'
      });
      setPrompts(response?.data || []);
    } catch (requestError) {
      setError(requestError.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load prompts.', 'gregius-data'));
    } finally {
      setIsLoading(false);
    }
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    loadPrompts();
  }, [loadPrompts]);
  const resetForm = () => {
    setForm(DEFAULT_FORM);
    setEditingPrompt(null);
  };
  const openCreateModal = () => {
    resetForm();
    setError(null);
    setSuccess(null);
    setIsModalOpen(true);
  };
  const openEditModal = prompt => {
    setEditingPrompt(prompt);
    setForm({
      title: prompt.title || '',
      prompt_type: prompt.prompt_type || 'system',
      status: prompt.status || 'draft',
      content: prompt.content || '',
      notes: prompt.notes || ''
    });
    setError(null);
    setSuccess(null);
    setIsModalOpen(true);
  };
  const closeModal = () => {
    setIsModalOpen(false);
    resetForm();
  };
  const updateFormField = (field, value) => {
    setForm(prev => ({
      ...prev,
      [field]: value
    }));
  };
  const savePrompt = async () => {
    if (!form.title.trim() || !form.content.trim()) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title and content are required.', 'gregius-data'));
      return;
    }
    setIsSubmitting(true);
    setError(null);
    setSuccess(null);
    try {
      const request = {
        path: editingPrompt ? `/gg-data/v1/prompts/${editingPrompt.id}` : '/gg-data/v1/prompts',
        method: editingPrompt ? 'PUT' : 'POST',
        data: {
          title: form.title,
          prompt_type: form.prompt_type,
          status: form.status,
          content: form.content,
          notes: form.notes
        }
      };
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()(request);
      await loadPrompts();
      setSuccess(editingPrompt ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt updated successfully.', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt created successfully.', 'gregius-data'));
      closeModal();
    } catch (requestError) {
      setError(requestError.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to save prompt.', 'gregius-data'));
    } finally {
      setIsSubmitting(false);
    }
  };
  const selectPrompt = async promptId => {
    setIsSubmitting(true);
    setError(null);
    setSuccess(null);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: `/gg-data/v1/prompts/${promptId}/activate`,
        method: 'POST'
      });
      await loadPrompts();
      setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt selected.', 'gregius-data'));
    } catch (requestError) {
      setError(requestError.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to select prompt.', 'gregius-data'));
    } finally {
      setIsSubmitting(false);
    }
  };
  const deletePrompt = async promptId => {
    if (!window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Delete this prompt?', 'gregius-data'))) {
      return;
    }
    setIsSubmitting(true);
    setError(null);
    setSuccess(null);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: `/gg-data/v1/prompts/${promptId}`,
        method: 'DELETE'
      });
      await loadPrompts();
      setSuccess((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt deleted.', 'gregius-data'));
    } catch (requestError) {
      setError(requestError.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to delete prompt.', 'gregius-data'));
    } finally {
      setIsSubmitting(false);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "gg-data-page gg-data-prompts-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '2rem 1.5rem 0',
        borderTop: '1px solid rgba(0, 0, 0, 0.1)'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        style: {
          display: 'flex',
          flexDirection: 'column'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalHeading, {
          level: 2,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompts', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manage RAG pipeline prompts.', 'gregius-data')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "primary",
        onClick: openCreateModal,
        disabled: isSubmitting,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add Prompt', 'gregius-data')
      })]
    }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
      status: "error",
      isDismissible: true,
      onRemove: () => setError(null),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        children: error
      })
    }), success && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
      status: "success",
      isDismissible: true,
      onRemove: () => setSuccess(null),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        children: success
      })
    }), isLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Card, {
      isRounded: false,
      style: {
        marginTop: 16
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardBody, {
        style: {
          textAlign: 'center',
          padding: '32px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          style: {
            marginTop: 12
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Loading prompts...', 'gregius-data')
        })]
      })
    }) : prompts.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Card, {
      isRounded: false,
      style: {
        marginTop: 16
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardBody, {
        style: {
          textAlign: 'center',
          padding: '60px 40px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          style: {
            color: '#646970',
            marginBottom: '24px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No prompts yet. Create your first prompt to start versioning prompt strategies.', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          variant: "secondary",
          onClick: openCreateModal,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add Your First Prompt', 'gregius-data')
        })]
      })
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: '2rem'
      },
      children: prompts.map(prompt => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Card, {
        isRounded: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardHeader, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
            style: {
              display: 'flex',
              flexWrap: 'wrap',
              gap: '1rem',
              justifyContent: 'space-between',
              alignItems: 'flex-start',
              width: '100%'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalHeading, {
              level: 3,
              style: {
                margin: 0
              },
              children: prompt.title
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.DropdownMenu, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt actions', 'gregius-data'),
              controls: [{
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Edit Prompt', 'gregius-data'),
                onClick: () => openEditModal(prompt)
              }, ...(prompt.status === 'published' && !prompt.selected ? [{
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select Prompt', 'gregius-data'),
                onClick: () => selectPrompt(prompt.id)
              }] : []), {
                title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Delete Prompt', 'gregius-data'),
                onClick: () => deletePrompt(prompt.id),
                className: 'has-text-color has-vivid-red-color'
              }]
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardBody, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalGrid, {
            columns: 3,
            gap: 4,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Version:', 'gregius-data')
              }), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: `v${prompt.version || 1}`
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt Type:', 'gregius-data')
              }), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: prompt.prompt_type || 'system'
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status:', 'gregius-data')
              }), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: prompt.status
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activity:', 'gregius-data')
              }), ' ', prompt.status === 'published' ? prompt.selected ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-success",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active', 'gregius-data')
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inactive', 'gregius-data')
              }) : null]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Modified:', 'gregius-data')
              }), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-info",
                children: prompt.modified ? new Date(prompt.modified).toLocaleString() : '-'
              })]
            }), prompt.is_factory && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Origin:', 'gregius-data')
              }), ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
                className: "components-badge is-secondary",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('System Default', 'gregius-data')
              })]
            })]
          }), prompt.content && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            style: {
              marginTop: '16px',
              whiteSpace: 'pre-wrap',
              color: '#3c434a'
            },
            children: prompt.content
          })]
        })]
      }, prompt.id))
    }), isModalOpen && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Modal, {
      title: editingPrompt ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Edit Prompt', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add New Prompt', 'gregius-data'),
      onRequestClose: closeModal,
      className: "gg-data-prompt-modal",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        style: {
          display: 'flex',
          flexDirection: 'column',
          gap: '16px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title', 'gregius-data'),
          value: form.title,
          onChange: value => updateFormField('title', value),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt Type', 'gregius-data'),
          value: form.prompt_type,
          options: promptTypeOptions,
          onChange: value => updateFormField('prompt_type', value),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status', 'gregius-data'),
          value: form.status,
          options: promptStatusOptions,
          onChange: value => updateFormField('status', value),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextareaControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Prompt Content', 'gregius-data'),
          value: form.content,
          rows: 10,
          onChange: value => updateFormField('content', value),
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextareaControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Notes', 'gregius-data'),
          value: form.notes,
          rows: 3,
          onChange: value => updateFormField('notes', value),
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '1rem',
            marginTop: '1rem',
            alignItems: 'center'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "primary",
            onClick: savePrompt,
            isBusy: isSubmitting,
            children: editingPrompt ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Save Changes', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Create Prompt', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "tertiary",
            onClick: closeModal,
            disabled: isSubmitting,
            style: {
              marginLeft: 'auto'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cancel', 'gregius-data')
          })]
        })]
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (PromptsPage);

/***/ },

/***/ "./src/scripts/dashboard/pages/SearchPage.js"
/*!***************************************************!*\
  !*** ./src/scripts/dashboard/pages/SearchPage.js ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _components_search_SearchSettingsCard__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../components/search/SearchSettingsCard */ "./src/scripts/dashboard/components/search/SearchSettingsCard.js");
/* harmony import */ var _components_search_SearchHealthCard__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../components/search/SearchHealthCard */ "./src/scripts/dashboard/components/search/SearchHealthCard.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Search Page Component
 *
 * Manages PostgreSQL Full-Text Search configuration and health monitoring.
 * Cards now manage their own state and load settings from global settings.
 *
 * @since 2.0.0
 */







const SearchPage = () => {
  // Get connections list for the settings card dropdown
  const {
    connections
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.useSelect)(select => ({
    connections: select('gg-data/connections').getConnectionsList()
  }), []);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "gg-data-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      style: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 16,
        padding: '2rem 1.5rem 0',
        borderTop: '1px solid rgba(0, 0, 0, 0.1)'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        style: {
          display: 'flex',
          flexWrap: 'wrap',
          gap: '1rem',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          width: '100%'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHeading, {
            level: 2,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Search', 'gregius-data')
          })
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_components_search_SearchHealthCard__WEBPACK_IMPORTED_MODULE_4__["default"], {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_components_search_SearchSettingsCard__WEBPACK_IMPORTED_MODULE_3__["default"], {
      connections: connections
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SearchPage);

/***/ },

/***/ "./src/scripts/dashboard/pages/SyncPage.js"
/*!*************************************************!*\
  !*** ./src/scripts/dashboard/pages/SyncPage.js ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _components_DatabaseSelector__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../components/DatabaseSelector */ "./src/scripts/dashboard/components/DatabaseSelector.js");
/* harmony import */ var _components_sync_TermsSyncCard__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/sync/TermsSyncCard */ "./src/scripts/dashboard/components/sync/TermsSyncCard.js");
/* harmony import */ var _components_sync_ContentSyncTable__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/sync/ContentSyncTable */ "./src/scripts/dashboard/components/sync/ContentSyncTable.js");
/* harmony import */ var _components_sync_RealTimeSyncCard__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../components/sync/RealTimeSyncCard */ "./src/scripts/dashboard/components/sync/RealTimeSyncCard.js");
/* harmony import */ var _components_sync_PostStatusesCard__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../components/sync/PostStatusesCard */ "./src/scripts/dashboard/components/sync/PostStatusesCard.js");
/* harmony import */ var _components_sync_RetryQueueCard__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../components/sync/RetryQueueCard */ "./src/scripts/dashboard/components/sync/RetryQueueCard.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__);
/**
 * Sync Page Component
 *
 * Handles all content sync, cleaning, and processing operations.
 */












const SyncPage = ({
  settings,
  isLoading,
  error,
  apiStatus
}) => {
  // Refresh trigger for coordinating child card updates
  const [refreshTrigger, setRefreshTrigger] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(0);

  // Use WordPress data stores (no duplicate calls!)
  const {
    connections,
    isLoadingConnections
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    connections: select('gg-data/connections').getConnectionsList(),
    isLoadingConnections: select('gg-data/connections').isLoading()
  }), []);
  const {
    selectedConnectionId
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    selectedConnectionId: select('gg-data/selected').getConnectionId()
  }), []);
  const {
    setConnection
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)('gg-data/selected');

  // Callback for when sync configuration changes (post types)
  const handlePostTypeChange = () => {
    setRefreshTrigger(prev => prev + 1);
    // Don't remount table - component handles optimistic updates internally
  };

  // Callback for when other settings change (statuses, real-time sync)
  const handleOtherConfigChange = () => {
    setRefreshTrigger(prev => prev + 1);
    // Don't remount table - status changes shouldn't affect post type toggles
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
    className: "gg-data-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
      style: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 16,
        padding: '2rem 1.5rem 0',
        borderTop: '1px solid rgba(0, 0, 0, 0.1)'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalHeading, {
        level: 2,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Synchronization', 'gregius-data')
      }), !isLoadingConnections && connections.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
        style: {
          minWidth: 220
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_components_DatabaseSelector__WEBPACK_IMPORTED_MODULE_4__["default"], {
          connections: connections,
          selectedConnectionId: selectedConnectionId,
          onSelect: setConnection
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: '1.5rem',
        padding: '1.5rem'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_components_sync_RetryQueueCard__WEBPACK_IMPORTED_MODULE_9__["default"], {
        selectedConnectionId: selectedConnectionId
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_components_sync_RealTimeSyncCard__WEBPACK_IMPORTED_MODULE_7__["default"], {
        selectedConnectionId: selectedConnectionId,
        onConfigurationChange: handleOtherConfigChange
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_components_sync_PostStatusesCard__WEBPACK_IMPORTED_MODULE_8__["default"], {
        selectedConnectionId: selectedConnectionId,
        onConfigurationChange: handleOtherConfigChange
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_components_sync_TermsSyncCard__WEBPACK_IMPORTED_MODULE_5__["default"], {
        selectedConnectionId: selectedConnectionId
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_components_sync_ContentSyncTable__WEBPACK_IMPORTED_MODULE_6__["default"], {
        selectedConnectionId: selectedConnectionId,
        refreshTrigger: refreshTrigger // Pass as prop for validation refresh
        ,
        onPostTypeChange: handlePostTypeChange // Notify when post types change
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SyncPage);

/***/ },

/***/ "./src/scripts/dashboard/pages/VectorsPage.js"
/*!****************************************************!*\
  !*** ./src/scripts/dashboard/pages/VectorsPage.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _components_DatabaseSelector__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/DatabaseSelector */ "./src/scripts/dashboard/components/DatabaseSelector.js");
/* harmony import */ var _components_vectors_AddModelModal__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/vectors/AddModelModal */ "./src/scripts/dashboard/components/vectors/AddModelModal.js");
/* harmony import */ var _components_vectors_APIEmbeddingCard__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../components/vectors/APIEmbeddingCard */ "./src/scripts/dashboard/components/vectors/APIEmbeddingCard.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
/**
 * Vectors Page Component
 *
 * Multi-model embedding management with card-per-model UI pattern.
 * Each embedding model gets its own card with independent actions.
 *
 * Architecture:
 * - Models are global (stored in MySQL wp_gg_settings)
 * - Connection-model associations per database (PostgreSQL connection_embedding_models)
 * - Each card shows model-specific vector status and actions
 * - "+ Add Model" button to add global models to this connection
 *
 * Card Types:
 * - APIEmbeddingCard: internal (HashingTF) and API-provided embeddings (OpenAI, Voyage AI, etc.)
 *
 * @since 1.0.0
 */










const VectorsPage = ({
  settings,
  isLoading,
  error,
  apiStatus
}) => {
  const [connectionModels, setConnectionModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [showAddModal, setShowAddModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [isLoadingModels, setIsLoadingModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  // Use WordPress data stores.
  const {
    connections,
    isLoadingConnections
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    connections: select('gg-data/connections').getConnectionsList(),
    isLoadingConnections: select('gg-data/connections').isLoading()
  }), []);
  const selectedConnectionId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => select('gg-data/selected').getConnectionId(), []);
  const {
    setConnection
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)('gg-data/selected');

  /**
   * Fetch connection models when connection changes
   */
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selectedConnectionId) {
      fetchConnectionModels();
    }
  }, [selectedConnectionId]);

  /**
   * Fetch active models for this connection
   */
  const fetchConnectionModels = async () => {
    setIsLoadingModels(true);
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/connections/${selectedConnectionId}/vectors/models`
      });
      if (response.success && response.data) {
        setConnectionModels(response.data);
      }
    } catch (err) {
      console.error('Failed to fetch connection models:', err);
    } finally {
      setIsLoadingModels(false);
    }
  };

  /**
   * Handle adding model to connection
   */
  const handleAddModel = async modelKey => {
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/connections/${selectedConnectionId}/vectors/models`,
        method: 'POST',
        data: {
          model_key: modelKey
        }
      });

      // Refresh connection models.
      fetchConnectionModels();
      setShowAddModal(false);
    } catch (err) {
      console.error('Failed to add model:', err);
      alert(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to add model', 'gregius-data'));
    }
  };

  /**
   * Handle removing model from connection
   */
  const handleRemoveModel = async (modelKey, vectorCount) => {
    if (vectorCount > 0) {
      alert(/* translators: %d: Number of vectors */
      (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cannot remove model with %d existing vectors. Delete vectors first.', 'gregius-data').replace('%d', vectorCount));
      return;
    }
    if (!confirm(/* translators: %s: Model key */
    (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove %s from this connection?', 'gregius-data').replace('%s', modelKey))) {
      return;
    }
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/gg-data/v1/connections/${selectedConnectionId}/vectors/models/${modelKey}`,
        method: 'DELETE'
      });

      // Refresh connection models.
      fetchConnectionModels();
    } catch (err) {
      console.error('Failed to remove model:', err);
      alert(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to remove model', 'gregius-data'));
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
    className: "gg-data-page",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      style: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 16,
        padding: '2rem 1.5rem 0',
        borderTop: '1px solid rgba(0, 0, 0, 0.1)'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        style: {
          display: 'flex',
          flexDirection: 'column'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalHeading, {
          level: 2,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Vector Generation', 'gregius-data')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
          className: "description",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Manage embedding models and generate vectors for semantic search.', 'gregius-data')
        })]
      }), !isLoadingConnections && connections.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        style: {
          minWidth: 220,
          display: 'flex',
          flexDirection: 'row',
          alignItems: 'end',
          gap: '1rem'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_DatabaseSelector__WEBPACK_IMPORTED_MODULE_5__["default"], {
          connections: connections,
          selectedConnectionId: selectedConnectionId,
          onSelect: setConnection
        }), selectedConnectionId && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          variant: "primary",
          onClick: () => setShowAddModal(true),
          style: {
            justifyContent: 'center'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Embedding Model', 'gregius-data')
        })]
      })]
    }), !isLoadingConnections && selectedConnectionId && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
      style: {
        padding: '1.5rem'
      },
      children: [isLoadingModels ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        style: {
          textAlign: 'center',
          padding: '40px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading models...', 'gregius-data')
        })]
      }) : connectionModels.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Card, {
        isRounded: false,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardBody, {
          style: {
            textAlign: 'center',
            padding: '60px 40px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
            style: {
              color: '#646970',
              marginBottom: '24px'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add your first embedding model to this connection to get started.', 'gregius-data')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "secondary",
            onClick: () => setShowAddModal(true),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add Your First Embedding Model', 'gregius-data')
          })]
        })
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
        className: "gg-data-model-cards",
        style: {
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(400px, 1fr))',
          gap: '20px'
        },
        children: connectionModels.map(model => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_vectors_APIEmbeddingCard__WEBPACK_IMPORTED_MODULE_7__["default"], {
          model: model,
          connection: selectedConnectionId,
          onRemove: handleRemoveModel,
          onRefresh: fetchConnectionModels
        }, model.model_key))
      }), showAddModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_vectors_AddModelModal__WEBPACK_IMPORTED_MODULE_6__["default"], {
        connection: selectedConnectionId,
        existingModels: connectionModels,
        onAdd: handleAddModel,
        onClose: () => setShowAddModal(false)
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VectorsPage);

/***/ },

/***/ "./src/scripts/dashboard/stores/connectionSelection/factory.js"
/*!*********************************************************************!*\
  !*** ./src/scripts/dashboard/stores/connectionSelection/factory.js ***!
  \*********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createConnectionSelectionStore: () => (/* binding */ createConnectionSelectionStore)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Connection Selection Store Factory
 * 
 * Creates isolated connection selection stores for different contexts (RAG, Search, Content/Sync).
 * Each store maintains its own selected connection with localStorage persistence.
 * 
 * @since 2.2.0
 */



/**
 * Factory function to create a connection selection store
 * 
 * @param {Object} config - Store configuration
 * @param {string} config.storeName - WordPress data store name (e.g., 'gg-data/rag-connection')
 * @param {string} config.storageKey - localStorage key (e.g., 'gg_data_rag_connection')
 * @param {string} config.actionPrefix - Action type prefix (e.g., 'RAG')
 * @returns {string} Store name
 */
const createConnectionSelectionStore = ({
  storeName,
  storageKey,
  actionPrefix
}) => {
  const DEFAULT_STATE = {
    selectedConnectionId: null
  };

  /**
   * Load initial state from localStorage
   */
  const getInitialState = () => {
    const stored = localStorage.getItem(storageKey);

    // Migration: Clear old numeric IDs (we now use connection names)
    if (stored && /^\d+$/.test(stored)) {
      localStorage.removeItem(storageKey);
      return {
        selectedConnectionId: null
      };
    }
    return {
      selectedConnectionId: stored && stored !== 'undefined' ? stored : null
    };
  };

  /**
   * Actions
   */
  const actions = {
    setConnection(id) {
      // Persist to localStorage
      // Treat empty string as null/cleared selection
      if (id && id !== '' && id !== 'undefined') {
        localStorage.setItem(storageKey, id);
      } else {
        localStorage.removeItem(storageKey);
        id = null; // Normalize empty values to null
      }
      return {
        type: `SET_${actionPrefix}_CONNECTION`,
        id
      };
    },
    clearConnection() {
      localStorage.removeItem(storageKey);
      return {
        type: `CLEAR_${actionPrefix}_CONNECTION`
      };
    }
  };

  /**
   * Selectors
   */
  const selectors = {
    getConnectionId(state) {
      return state.selectedConnectionId;
    },
    hasConnection(state) {
      return state.selectedConnectionId !== null && state.selectedConnectionId !== '';
    }
  };

  /**
   * Reducer
   */
  const reducer = (state = getInitialState(), action) => {
    switch (action.type) {
      case `SET_${actionPrefix}_CONNECTION`:
        return {
          ...state,
          selectedConnectionId: action.id
        };
      case `CLEAR_${actionPrefix}_CONNECTION`:
        return {
          ...state,
          selectedConnectionId: null
        };
      default:
        return state;
    }
  };

  // Register the store
  (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.registerStore)(storeName, {
    reducer,
    actions,
    selectors
  });
  return storeName;
};

/***/ },

/***/ "./src/scripts/dashboard/stores/connections/actions.js"
/*!*************************************************************!*\
  !*** ./src/scripts/dashboard/stores/connections/actions.js ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   addConnection: () => (/* binding */ addConnection),
/* harmony export */   createConnection: () => (/* binding */ createConnection),
/* harmony export */   deleteConnection: () => (/* binding */ deleteConnection),
/* harmony export */   getConnectionHealth: () => (/* binding */ getConnectionHealth),
/* harmony export */   removeConnection: () => (/* binding */ removeConnection),
/* harmony export */   setConnections: () => (/* binding */ setConnections),
/* harmony export */   setError: () => (/* binding */ setError),
/* harmony export */   setLoading: () => (/* binding */ setLoading),
/* harmony export */   testConnection: () => (/* binding */ testConnection),
/* harmony export */   updateConnection: () => (/* binding */ updateConnection),
/* harmony export */   updateConnectionData: () => (/* binding */ updateConnectionData)
/* harmony export */ });
/**
 * Connections Store - Actions
 * 
 * Action creators for connections store
 */

const setConnections = connections => ({
  type: 'SET_CONNECTIONS',
  connections
});
const setLoading = isLoading => ({
  type: 'SET_LOADING',
  isLoading
});
const setError = error => ({
  type: 'SET_ERROR',
  error
});
const addConnection = (name, connection) => ({
  type: 'ADD_CONNECTION',
  name,
  connection
});
const updateConnectionData = (name, updates) => ({
  type: 'UPDATE_CONNECTION',
  name,
  updates
});
const removeConnection = name => ({
  type: 'DELETE_CONNECTION',
  name
});

// Action creators for async operations (used by resolvers)
function* createConnection(connectionData) {
  const {
    name,
    ...config
  } = connectionData;
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: '/gg-data/v1/connections',
        method: 'POST',
        data: connectionData
      }
    };
    if (response.success) {
      yield addConnection(name, response.data);
      return response.data;
    } else {
      throw new Error(response.message || 'Failed to create connection');
    }
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}
function* updateConnection(name, updates) {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: `/gg-data/v1/connections/${name}`,
        method: 'PUT',
        data: updates
      }
    };
    if (response.success) {
      yield updateConnectionData(name, response.data);
      return response.data;
    } else {
      throw new Error(response.message || 'Failed to update connection');
    }
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}
function* deleteConnection(name) {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: `/gg-data/v1/connections/${name}`,
        method: 'DELETE'
      }
    };
    if (response.success) {
      yield removeConnection(name);
      return true;
    } else {
      throw new Error(response.message || 'Failed to delete connection');
    }
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}
function* testConnection(name) {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: `/gg-data/v1/connections/${name}/test`,
        method: 'POST'
      }
    };
    return response;
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}
function* getConnectionHealth(name) {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: `/gg-data/v1/connections/${name}/health`,
        method: 'GET'
      }
    };
    return response;
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}

/***/ },

/***/ "./src/scripts/dashboard/stores/connections/index.js"
/*!***********************************************************!*\
  !*** ./src/scripts/dashboard/stores/connections/index.js ***!
  \***********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./actions */ "./src/scripts/dashboard/stores/connections/actions.js");
/* harmony import */ var _selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./selectors */ "./src/scripts/dashboard/stores/connections/selectors.js");
/* harmony import */ var _resolvers__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./resolvers */ "./src/scripts/dashboard/stores/connections/resolvers.js");
/**
 * Connections Store
 * 
 * WordPress Data Store for managing PostgreSQL database connections
 */





const DEFAULT_STATE = {
  connections: {},
  isLoading: false,
  error: null
};
const reducer = (state = DEFAULT_STATE, action) => {
  switch (action.type) {
    case 'SET_CONNECTIONS':
      return {
        ...state,
        connections: action.connections,
        isLoading: false
      };
    case 'SET_LOADING':
      return {
        ...state,
        isLoading: action.isLoading
      };
    case 'SET_ERROR':
      return {
        ...state,
        error: action.error,
        isLoading: false
      };
    case 'ADD_CONNECTION':
      return {
        ...state,
        connections: {
          ...state.connections,
          [action.name]: action.connection
        }
      };
    case 'UPDATE_CONNECTION':
      return {
        ...state,
        connections: {
          ...state.connections,
          [action.name]: {
            ...state.connections[action.name],
            ...action.updates
          }
        }
      };
    case 'DELETE_CONNECTION':
      const {
        [action.name]: deleted,
        ...remainingConnections
      } = state.connections;
      return {
        ...state,
        connections: remainingConnections
      };
    default:
      return state;
  }
};

// Controls for handling generator actions
const controls = {
  API_FETCH({
    request
  }) {
    return window.wp.apiFetch(request);
  },
  SELECT({
    storeName,
    selectorName,
    args = []
  }) {
    return window.wp.data.select(storeName)[selectorName](...args);
  }
};

// Register the store
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.registerStore)('gg-data/connections', {
  reducer,
  actions: _actions__WEBPACK_IMPORTED_MODULE_1__,
  selectors: _selectors__WEBPACK_IMPORTED_MODULE_2__,
  resolvers: _resolvers__WEBPACK_IMPORTED_MODULE_3__,
  controls
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ('gg-data/connections');

/***/ },

/***/ "./src/scripts/dashboard/stores/connections/resolvers.js"
/*!***************************************************************!*\
  !*** ./src/scripts/dashboard/stores/connections/resolvers.js ***!
  \***************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getConnection: () => (/* binding */ getConnection),
/* harmony export */   getConnections: () => (/* binding */ getConnections),
/* harmony export */   getConnectionsList: () => (/* binding */ getConnectionsList),
/* harmony export */   invalidateConnections: () => (/* binding */ invalidateConnections)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./actions */ "./src/scripts/dashboard/stores/connections/actions.js");
/**
 * Connections Store - Resolvers
 * 
 * Resolvers handle async data fetching for selectors
 */




// Resolver for getConnections selector
function* getConnections() {
  // Get current state via select
  const currentConnections = yield {
    type: 'SELECT',
    storeName: 'gg-data/connections',
    selectorName: 'getConnections'
  };

  // If already loaded, return cached data
  if (currentConnections && Object.keys(currentConnections).length > 0) {
    return;
  }
  yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setLoading)(true);
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: '/gg-data/v1/connections',
        method: 'GET'
      }
    };
    if (response.success) {
      yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setConnections)(response.data || {});
    } else {
      throw new Error(response.message || 'Failed to fetch connections');
    }
  } catch (error) {
    yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setError)(error.message);
    yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setConnections)({});
  } finally {
    yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setLoading)(false);
  }
}

// Resolver for getConnection selector
function* getConnection(name) {
  // First ensure connections are loaded
  const connections = yield {
    type: 'SELECT',
    storeName: 'gg-data/connections',
    selectorName: 'getConnections'
  };

  // If no connections, trigger fetch
  if (!connections || Object.keys(connections).length === 0) {
    yield* getConnections();
  }
}

// Resolver for getConnectionsList selector
// This ensures data is fetched when using getConnectionsList
function* getConnectionsList() {
  // Delegate to getConnections resolver since getConnectionsList
  // is just a transformed view of the same data
  yield* getConnections();
}

// Helper to invalidate cache and force refetch
function* invalidateConnections() {
  yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setLoading)(true);
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: '/gg-data/v1/connections',
        method: 'GET'
      }
    };
    if (response.success) {
      yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setConnections)(response.data || {});
    } else {
      throw new Error(response.message || 'Failed to fetch connections');
    }
  } catch (error) {
    yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setError)(error.message);
  } finally {
    yield (0,_actions__WEBPACK_IMPORTED_MODULE_1__.setLoading)(false);
  }
}

/***/ },

/***/ "./src/scripts/dashboard/stores/connections/selectors.js"
/*!***************************************************************!*\
  !*** ./src/scripts/dashboard/stores/connections/selectors.js ***!
  \***************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getActiveConnections: () => (/* binding */ getActiveConnections),
/* harmony export */   getConnection: () => (/* binding */ getConnection),
/* harmony export */   getConnectionByName: () => (/* binding */ getConnectionByName),
/* harmony export */   getConnectionCount: () => (/* binding */ getConnectionCount),
/* harmony export */   getConnections: () => (/* binding */ getConnections),
/* harmony export */   getConnectionsList: () => (/* binding */ getConnectionsList),
/* harmony export */   getDefaultConnection: () => (/* binding */ getDefaultConnection),
/* harmony export */   getError: () => (/* binding */ getError),
/* harmony export */   hasConnections: () => (/* binding */ hasConnections),
/* harmony export */   isLoading: () => (/* binding */ isLoading)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Connections Store - Selectors
 * 
 * Selector functions for accessing connections state
 */



// Basic selectors
const getConnections = state => state.connections;
const isLoading = state => state.isLoading;
const getError = state => state.error;

// Memoized selectors
const getConnection = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)((state, name) => state.connections[name], (state, name) => [state.connections, name]);
const getConnectionsList = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => Object.entries(state.connections).map(([name, config]) => ({
  name,
  ...config
})), state => [state.connections]);
const hasConnections = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => Object.keys(state.connections).length > 0, state => [state.connections]);
const getConnectionCount = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => Object.keys(state.connections).length, state => [state.connections]);
const getActiveConnections = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => Object.entries(state.connections).filter(([, config]) => config.is_active).reduce((acc, [name, config]) => ({
  ...acc,
  [name]: config
}), {}), state => [state.connections]);
const getDefaultConnection = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => {
  const defaultEntry = Object.entries(state.connections).find(([, config]) => config.is_default);
  return defaultEntry ? defaultEntry[0] : null;
}, state => [state.connections]);
const getConnectionByName = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)((state, name) => {
  const entry = Object.entries(state.connections).find(([connName]) => connName === name);
  return entry ? {
    name: entry[0],
    ...entry[1]
  } : null;
}, (state, name) => [state.connections, name]);

/***/ },

/***/ "./src/scripts/dashboard/stores/logs/actions.js"
/*!******************************************************!*\
  !*** ./src/scripts/dashboard/stores/logs/actions.js ***!
  \******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   exportLogs: () => (/* binding */ exportLogs),
/* harmony export */   fetchLogs: () => (/* binding */ fetchLogs),
/* harmony export */   fetchSettings: () => (/* binding */ fetchSettings),
/* harmony export */   fetchStats: () => (/* binding */ fetchStats),
/* harmony export */   purgeLogs: () => (/* binding */ purgeLogs),
/* harmony export */   setAutoRefresh: () => (/* binding */ setAutoRefresh),
/* harmony export */   setError: () => (/* binding */ setError),
/* harmony export */   setFilters: () => (/* binding */ setFilters),
/* harmony export */   setLoading: () => (/* binding */ setLoading),
/* harmony export */   setLogs: () => (/* binding */ setLogs),
/* harmony export */   setPagination: () => (/* binding */ setPagination),
/* harmony export */   setSettings: () => (/* binding */ setSettings),
/* harmony export */   setStats: () => (/* binding */ setStats),
/* harmony export */   updateSettings: () => (/* binding */ updateSettings)
/* harmony export */ });
/**
 * Logs Store - Actions
 * 
 * Action creators for logs store
 */

const setLogs = logs => ({
  type: 'SET_LOGS',
  logs
});
const setPagination = pagination => ({
  type: 'SET_PAGINATION',
  pagination
});
const setStats = stats => ({
  type: 'SET_STATS',
  stats
});
const setSettings = settings => ({
  type: 'SET_SETTINGS',
  settings
});
const setLoading = isLoading => ({
  type: 'SET_LOADING',
  isLoading
});
const setError = error => ({
  type: 'SET_ERROR',
  error
});
const setAutoRefresh = autoRefresh => ({
  type: 'SET_AUTO_REFRESH',
  autoRefresh
});
const setFilters = filters => ({
  type: 'SET_FILTERS',
  filters
});

// Async action for fetching logs
function* fetchLogs(filters = {}) {
  yield setLoading(true);
  yield setError(null);
  try {
    // Build query parameters
    const params = new URLSearchParams();
    if (filters.page) params.append('page', filters.page);
    if (filters.per_page) params.append('per_page', filters.per_page);
    if (filters.level) params.append('level', filters.level);
    if (filters.component) params.append('component', filters.component);
    if (filters.connection_id) params.append('connection_id', filters.connection_id);
    if (filters.search) params.append('search', filters.search);
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    if (filters.orderby) params.append('orderby', filters.orderby);
    if (filters.order) params.append('order', filters.order);
    const path = `/gg-data/v1/logs${params.toString() ? '?' + params.toString() : ''}`;
    const response = yield {
      type: 'API_FETCH',
      request: {
        path,
        method: 'GET'
      }
    };
    if (response.success) {
      yield setLogs(response.data.logs || []);
      yield setPagination({
        page: response.data.page || 1,
        perPage: response.data.per_page || 50,
        totalPages: response.data.total_pages || 1,
        totalItems: response.data.total_items || 0
      });
    } else {
      throw new Error(response.message || 'Failed to fetch logs');
    }
  } catch (error) {
    yield setError(error.message);
    yield setLogs([]);
    yield setPagination({
      page: 1,
      perPage: 50,
      totalPages: 0,
      totalItems: 0
    });
  } finally {
    yield setLoading(false);
  }
}

// Async action for fetching stats
function* fetchStats() {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: '/gg-data/v1/logs/stats',
        method: 'GET'
      }
    };
    if (response.success) {
      yield setStats(response.data || {});
    } else {
      throw new Error(response.message || 'Failed to fetch stats');
    }
  } catch (error) {
    yield setError(error.message);
  }
}

// Async action for fetching settings
function* fetchSettings() {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: '/gg-data/v1/logs/settings',
        method: 'GET'
      }
    };
    if (response.success) {
      yield setSettings(response.data || {});
    } else {
      throw new Error(response.message || 'Failed to fetch settings');
    }
  } catch (error) {
    yield setError(error.message);
  }
}

// Async action for updating settings
function* updateSettings(updates) {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: '/gg-data/v1/logs/settings',
        method: 'POST',
        data: updates
      }
    };
    if (response.success) {
      yield setSettings(response.data || {});
      return response.data;
    } else {
      throw new Error(response.message || 'Failed to update settings');
    }
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}

// Async action for purging logs
function* purgeLogs(days = 30) {
  try {
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: `/gg-data/v1/logs/purge?days=${days}`,
        method: 'DELETE'
      }
    };
    if (response.success) {
      return response.data;
    } else {
      throw new Error(response.message || 'Failed to purge logs');
    }
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}

// Async action for exporting logs
function* exportLogs(filters = {}, format = 'csv') {
  try {
    const params = new URLSearchParams({
      format
    });
    if (filters.level) params.append('level', filters.level);
    if (filters.component) params.append('component', filters.component);
    if (filters.connection_id) params.append('connection_id', filters.connection_id);
    if (filters.search) params.append('search', filters.search);
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    const response = yield {
      type: 'API_FETCH',
      request: {
        path: `/gg-data/v1/logs/export?${params.toString()}`,
        method: 'GET',
        parse: false // Don't parse as JSON
      }
    };
    return response;
  } catch (error) {
    yield setError(error.message);
    throw error;
  }
}

/***/ },

/***/ "./src/scripts/dashboard/stores/logs/index.js"
/*!****************************************************!*\
  !*** ./src/scripts/dashboard/stores/logs/index.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./actions */ "./src/scripts/dashboard/stores/logs/actions.js");
/* harmony import */ var _selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./selectors */ "./src/scripts/dashboard/stores/logs/selectors.js");
/* harmony import */ var _resolvers__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./resolvers */ "./src/scripts/dashboard/stores/logs/resolvers.js");
/**
 * Logs Store
 * 
 * WordPress Data Store for managing plugin logs and monitoring
 */





const DEFAULT_STATE = {
  logs: [],
  pagination: {
    page: 1,
    perPage: 50,
    totalPages: 0,
    totalItems: 0
  },
  stats: {
    total: 0,
    by_level: {},
    by_component: {}
  },
  settings: {
    logging_enabled: true,
    log_level: 'info',
    retention_days: 30,
    levels: [],
    components: []
  },
  isLoading: false,
  error: null,
  autoRefresh: false
};
const reducer = (state = DEFAULT_STATE, action) => {
  switch (action.type) {
    case 'SET_LOGS':
      return {
        ...state,
        logs: action.logs,
        isLoading: false
      };
    case 'SET_PAGINATION':
      return {
        ...state,
        pagination: action.pagination
      };
    case 'SET_STATS':
      return {
        ...state,
        stats: action.stats
      };
    case 'SET_SETTINGS':
      return {
        ...state,
        settings: action.settings
      };
    case 'SET_LOADING':
      return {
        ...state,
        isLoading: action.isLoading
      };
    case 'SET_ERROR':
      return {
        ...state,
        error: action.error,
        isLoading: false
      };
    case 'SET_AUTO_REFRESH':
      return {
        ...state,
        autoRefresh: action.autoRefresh
      };
    case 'SET_FILTERS':
      return {
        ...state,
        filters: action.filters
      };
    default:
      return state;
  }
};

// Controls for handling generator actions
const controls = {
  API_FETCH({
    request
  }) {
    return window.wp.apiFetch(request);
  },
  SELECT({
    storeName,
    selectorName,
    args = []
  }) {
    return window.wp.data.select(storeName)[selectorName](...args);
  }
};

// Register the store
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.registerStore)('gg-data/logs', {
  reducer,
  actions: _actions__WEBPACK_IMPORTED_MODULE_1__,
  selectors: _selectors__WEBPACK_IMPORTED_MODULE_2__,
  resolvers: _resolvers__WEBPACK_IMPORTED_MODULE_3__,
  controls
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ('gg-data/logs');

/***/ },

/***/ "./src/scripts/dashboard/stores/logs/resolvers.js"
/*!********************************************************!*\
  !*** ./src/scripts/dashboard/stores/logs/resolvers.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getLogs: () => (/* binding */ getLogs),
/* harmony export */   getSettings: () => (/* binding */ getSettings),
/* harmony export */   getStats: () => (/* binding */ getStats)
/* harmony export */ });
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./actions */ "./src/scripts/dashboard/stores/logs/actions.js");
/**
 * Logs Store - Resolvers
 * 
 * Resolvers handle async data fetching for selectors
 */



// Resolver for getLogs selector
function* getLogs(filters = {}) {
  // Get current state
  const currentLogs = yield {
    type: 'SELECT',
    storeName: 'gg-data/logs',
    selectorName: 'getLogs'
  };

  // Only fetch if not already loaded or filters changed
  if (!currentLogs || currentLogs.length === 0) {
    yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setLoading)(true);
    try {
      const params = new URLSearchParams();
      if (filters.page) params.append('page', filters.page);
      if (filters.per_page) params.append('per_page', filters.per_page);
      if (filters.level) params.append('level', filters.level);
      if (filters.component) params.append('component', filters.component);
      if (filters.connection_id) params.append('connection_id', filters.connection_id);
      if (filters.search) params.append('search', filters.search);
      if (filters.date_from) params.append('date_from', filters.date_from);
      if (filters.date_to) params.append('date_to', filters.date_to);
      const path = `/gg-data/v1/logs${params.toString() ? '?' + params.toString() : ''}`;
      const response = yield {
        type: 'API_FETCH',
        request: {
          path,
          method: 'GET'
        }
      };
      if (response.success) {
        yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setLogs)(response.data.logs || []);
      } else {
        throw new Error(response.message || 'Failed to fetch logs');
      }
    } catch (error) {
      yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setError)(error.message);
    } finally {
      yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setLoading)(false);
    }
  }
}

// Resolver for getStats selector
function* getStats() {
  // Get current state
  const currentStats = yield {
    type: 'SELECT',
    storeName: 'gg-data/logs',
    selectorName: 'getStats'
  };

  // Only fetch if not already loaded
  if (!currentStats || Object.keys(currentStats).length === 0) {
    try {
      const response = yield {
        type: 'API_FETCH',
        request: {
          path: '/gg-data/v1/logs/stats',
          method: 'GET'
        }
      };
      if (response.success) {
        yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setStats)(response.data || {});
      } else {
        throw new Error(response.message || 'Failed to fetch stats');
      }
    } catch (error) {
      yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setError)(error.message);
    }
  }
}

// Resolver for getSettings selector
function* getSettings() {
  // Get current state
  const currentSettings = yield {
    type: 'SELECT',
    storeName: 'gg-data/logs',
    selectorName: 'getSettings'
  };

  // Only fetch if not already loaded
  if (!currentSettings || Object.keys(currentSettings).length === 0) {
    try {
      const response = yield {
        type: 'API_FETCH',
        request: {
          path: '/gg-data/v1/logs/settings',
          method: 'GET'
        }
      };
      if (response.success) {
        yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setSettings)(response.data || {});
      } else {
        throw new Error(response.message || 'Failed to fetch settings');
      }
    } catch (error) {
      yield (0,_actions__WEBPACK_IMPORTED_MODULE_0__.setError)(error.message);
    }
  }
}

/***/ },

/***/ "./src/scripts/dashboard/stores/logs/selectors.js"
/*!********************************************************!*\
  !*** ./src/scripts/dashboard/stores/logs/selectors.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getAutoRefresh: () => (/* binding */ getAutoRefresh),
/* harmony export */   getComponentCounts: () => (/* binding */ getComponentCounts),
/* harmony export */   getComponentOptions: () => (/* binding */ getComponentOptions),
/* harmony export */   getError: () => (/* binding */ getError),
/* harmony export */   getLevelCounts: () => (/* binding */ getLevelCounts),
/* harmony export */   getLevelOptions: () => (/* binding */ getLevelOptions),
/* harmony export */   getLogById: () => (/* binding */ getLogById),
/* harmony export */   getLogCount: () => (/* binding */ getLogCount),
/* harmony export */   getLogs: () => (/* binding */ getLogs),
/* harmony export */   getLogsWithContext: () => (/* binding */ getLogsWithContext),
/* harmony export */   getPagination: () => (/* binding */ getPagination),
/* harmony export */   getSettings: () => (/* binding */ getSettings),
/* harmony export */   getStats: () => (/* binding */ getStats),
/* harmony export */   isLoading: () => (/* binding */ isLoading)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Logs Store - Selectors
 * 
 * Selector functions for accessing logs state
 */



// Basic selectors
const getLogs = state => state.logs || [];
const getPagination = state => state.pagination || {
  page: 1,
  perPage: 50,
  totalPages: 0,
  totalItems: 0
};
const getStats = state => state.stats || {
  total: 0,
  by_level: {},
  by_component: {}
};
const getSettings = state => state.settings || {
  logging_enabled: true,
  log_level: 'info',
  retention_days: 30,
  levels: [],
  components: []
};
const isLoading = state => state.isLoading || false;
const getError = state => state.error || null;
const getAutoRefresh = state => state.autoRefresh || false;

// Memoized selectors
const getLogCount = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => state.stats && state.stats.total || 0, state => [state.stats]);
const getLevelCounts = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => state.stats && state.stats.by_level || {}, state => [state.stats]);
const getComponentCounts = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => state.stats && state.stats.by_component || {}, state => [state.stats]);
const getLevelOptions = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => {
  const levels = state.settings && state.settings.levels || [];
  const counts = state.stats && state.stats.by_level || {};
  return levels.map(level => ({
    label: `${level.charAt(0).toUpperCase() + level.slice(1)} (${counts[level] || 0})`,
    value: level
  }));
}, state => [state.settings, state.stats]);
const getComponentOptions = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => {
  const components = state.settings && state.settings.components || [];
  const counts = state.stats && state.stats.by_component || {};
  return components.map(component => ({
    label: `${component.charAt(0).toUpperCase() + component.slice(1)} (${counts[component] || 0})`,
    value: component
  }));
}, state => [state.settings, state.stats]);
const getLogById = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)((state, logId) => {
  if (!state.logs) return null;
  return state.logs.find(log => log.id === logId) || null;
}, (state, logId) => [state.logs, logId]);
const getLogsWithContext = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createSelector)(state => {
  if (!state.logs) return [];
  return state.logs.map(log => ({
    ...log,
    contextData: log.context ? typeof log.context === 'string' ? JSON.parse(log.context) : log.context : {}
  }));
}, state => [state.logs]);

/***/ },

/***/ "./src/scripts/dashboard/stores/searchConnection/index.js"
/*!****************************************************************!*\
  !*** ./src/scripts/dashboard/stores/searchConnection/index.js ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _connectionSelection_factory__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../connectionSelection/factory */ "./src/scripts/dashboard/stores/connectionSelection/factory.js");
/**
 * Search Connection Store
 * 
 * Manages connection selection specifically for the Search page.
 * Independent from Content/Sync connection selection.
 * 
 * @since 2.1.0
 */


const store = (0,_connectionSelection_factory__WEBPACK_IMPORTED_MODULE_0__.createConnectionSelectionStore)({
  storeName: 'gg-data/search-connection',
  storageKey: 'gg_data_search_connection',
  actionPrefix: 'SEARCH'
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (store);

/***/ },

/***/ "./src/scripts/dashboard/stores/selectedConnection/index.js"
/*!******************************************************************!*\
  !*** ./src/scripts/dashboard/stores/selectedConnection/index.js ***!
  \******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _connectionSelection_factory__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../connectionSelection/factory */ "./src/scripts/dashboard/stores/connectionSelection/factory.js");
/**
 * Selected Connection Store
 * 
 * WordPress Data Store for managing the currently selected database connection.
 * Used by Content and Sync pages.
 * Persists selection in localStorage.
 * 
 * @since 1.0.0
 */


const store = (0,_connectionSelection_factory__WEBPACK_IMPORTED_MODULE_0__.createConnectionSelectionStore)({
  storeName: 'gg-data/selected',
  storageKey: 'gg_data_selected_connection',
  actionPrefix: 'SELECTED'
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (store);

/***/ },

/***/ "./src/scripts/dashboard/stores/settings/index.js"
/*!********************************************************!*\
  !*** ./src/scripts/dashboard/stores/settings/index.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Settings Store
 * 
 * WordPress Data Store for managing plugin settings
 */



const DEFAULT_STATE = {
  settings: {},
  isLoading: false,
  error: null
};
const actions = {
  setSettings(settings) {
    return {
      type: 'SET_SETTINGS',
      settings
    };
  },
  setLoading(isLoading) {
    return {
      type: 'SET_LOADING',
      isLoading
    };
  },
  setError(error) {
    return {
      type: 'SET_ERROR',
      error
    };
  },
  updateSettingData(key, value) {
    return {
      type: 'UPDATE_SETTING',
      key,
      value
    };
  },
  removeSettingData(key) {
    return {
      type: 'DELETE_SETTING',
      key
    };
  },
  *updateSetting(key, value) {
    try {
      const response = yield _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: `/gg-data/v1/settings/${key}`,
        method: 'PUT',
        data: {
          setting_value: value
        }
      });
      if (response.success) {
        yield actions.updateSettingData(key, value);
        return true;
      } else {
        throw new Error(response.message || 'Failed to update setting');
      }
    } catch (error) {
      yield actions.setError(error.message);
      throw error;
    }
  },
  *deleteSetting(key) {
    try {
      const response = yield _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: `/gg-data/v1/settings/${key}`,
        method: 'DELETE'
      });
      if (response.success) {
        yield actions.removeSettingData(key);
        return true;
      } else {
        throw new Error(response.message || 'Failed to delete setting');
      }
    } catch (error) {
      yield actions.setError(error.message);
      throw error;
    }
  },
  *addSetting(category, key, value) {
    try {
      const response = yield _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: '/gg-data/v1/settings',
        method: 'POST',
        data: {
          category,
          setting_key: key,
          setting_value: value
        }
      });
      if (response.success) {
        yield actions.updateSettingData(key, value);
        return response;
      } else {
        throw new Error(response.message || 'Failed to add setting');
      }
    } catch (error) {
      yield actions.setError(error.message);
      throw error;
    }
  }
};
const selectors = {
  getSettings(state) {
    return state.settings;
  },
  getSetting(state, key, defaultValue = null) {
    return state.settings[key] !== undefined ? state.settings[key] : defaultValue;
  },
  isLoading(state) {
    return state.isLoading;
  },
  getError(state) {
    return state.error;
  },
  hasSettings(state) {
    return Object.keys(state.settings).length > 0;
  },
  getSettingsByCategory(state, category) {
    return Object.entries(state.settings).filter(([key]) => key.startsWith(category + '_')).reduce((acc, [key, value]) => ({
      ...acc,
      [key]: value
    }), {});
  }
};
const resolvers = {
  *getSettings() {
    const settings = yield {
      type: 'GET_SETTINGS_FROM_STATE'
    };

    // If already loaded, return cached data
    if (Object.keys(settings).length > 0) {
      return;
    }
    yield actions.setLoading(true);
    try {
      const response = yield _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: '/gg-data/v1/settings',
        method: 'GET'
      });
      if (response && response.settings) {
        yield actions.setSettings(response.settings);
      } else {
        yield actions.setSettings({});
      }
    } catch (error) {
      yield actions.setError(error.message);
      yield actions.setSettings({});
    } finally {
      yield actions.setLoading(false);
    }
  },
  *getSetting(key) {
    // Trigger getSettings if not loaded
    yield {
      type: 'RESOLVE_GET_SETTINGS'
    };
  }
};
const reducer = (state = DEFAULT_STATE, action) => {
  switch (action.type) {
    case 'SET_SETTINGS':
      return {
        ...state,
        settings: action.settings,
        isLoading: false
      };
    case 'SET_LOADING':
      return {
        ...state,
        isLoading: action.isLoading
      };
    case 'SET_ERROR':
      return {
        ...state,
        error: action.error,
        isLoading: false
      };
    case 'UPDATE_SETTING':
      return {
        ...state,
        settings: {
          ...state.settings,
          [action.key]: action.value
        }
      };
    case 'DELETE_SETTING':
      const {
        [action.key]: deleted,
        ...remainingSettings
      } = state.settings;
      return {
        ...state,
        settings: remainingSettings
      };
    case 'GET_SETTINGS_FROM_STATE':
      return state.settings;
    default:
      return state;
  }
};
const controls = {
  API_FETCH({
    request
  }) {
    return window.wp.apiFetch(request);
  },
  GET_SETTINGS_FROM_STATE() {
    return {
      type: 'NOOP'
    };
  },
  RESOLVE_GET_SETTINGS() {
    return {
      type: 'NOOP'
    };
  }
};

// Register the store
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.registerStore)('gg-data/settings', {
  reducer,
  actions,
  selectors,
  resolvers,
  controls
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ('gg-data/settings');

/***/ },

/***/ "./src/scripts/dashboard/utils/api.js"
/*!********************************************!*\
  !*** ./src/scripts/dashboard/utils/api.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   API_CONFIG: () => (/* binding */ API_CONFIG),
/* harmony export */   checkApiConnection: () => (/* binding */ checkApiConnection),
/* harmony export */   connectionAPI: () => (/* binding */ connectionAPI),
/* harmony export */   handleAPIError: () => (/* binding */ handleAPIError),
/* harmony export */   settingsAPI: () => (/* binding */ settingsAPI)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * API Utilities for Gregius PostgreSQL Dashboard
 * 
 * Provides WordPress REST API integration for the React dashboard.
 * All API calls follow WordPress authentication and nonce patterns.
 */



/**
 * Base API path for Gregius PostgreSQL endpoints
 */
const API_BASE = '/gg-data/v1';

/**
 * Settings API endpoints
 */
const settingsAPI = {
  /**
   * Get all settings
   * @param {Object} params - Query parameters
   * @returns {Promise} API response
   */
  getAll: (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    const url = `${API_BASE}/settings${queryString ? `?${queryString}` : ''}`;
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: url
    });
  },
  /**
   * Get single setting by ID
   * @param {number} id - Setting ID
   * @returns {Promise} API response
   */
  get: id => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/${id}`
    });
  },
  /**
   * Create new setting
   * @param {Object} data - Setting data
   * @returns {Promise} API response
   */
  create: data => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings`,
      method: 'POST',
      data
    });
  },
  /**
   * Update existing setting
   * @param {number} id - Setting ID
   * @param {Object} data - Updated setting data
   * @returns {Promise} API response
   */
  update: (id, data) => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/${id}`,
      method: 'PUT',
      data
    });
  },
  /**
   * Delete setting
   * @param {number} id - Setting ID
   * @returns {Promise} API response
   */
  delete: id => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/${id}`,
      method: 'DELETE'
    });
  },
  /**
   * Bulk update multiple settings
   * @param {Array} settings - Array of setting objects
   * @returns {Promise} API response
   */
  bulkUpdate: settings => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/bulk`,
      method: 'POST',
      data: {
        settings
      }
    });
  },
  /**
   * Get settings by category
   * @param {string} category - Setting category
   * @returns {Promise} API response
   */
  getByCategory: category => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings?category=${encodeURIComponent(category)}`
    });
  },
  /**
   * Get settings by connection name
   * @param {string} connectionName - Connection name
   * @returns {Promise} API response
   */
  getByConnection: connectionName => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings?connection_name=${encodeURIComponent(connectionName)}`
    });
  }
};

/**
 * Connection-specific API endpoints
 */
const connectionAPI = {
  /**
   * Get all connections (settings grouped by connection_name)
   * @returns {Promise} API response
   */
  getAll: () => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/connections`
    });
  },
  /**
   * Get specific connection settings
   * @param {string} connectionName - Connection name
   * @returns {Promise} API response
   */
  get: connectionName => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/connections/${encodeURIComponent(connectionName)}`
    });
  },
  /**
   * Update connection settings
   * @param {string} connectionName - Connection name
   * @param {Object} settings - Connection settings
   * @returns {Promise} API response
   */
  update: (connectionName, settings) => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/connections/${encodeURIComponent(connectionName)}`,
      method: 'PUT',
      data: {
        settings
      }
    });
  },
  /**
   * Delete connection and all its settings
   * @param {string} connectionName - Connection name
   * @returns {Promise} API response
   */
  delete: connectionName => {
    return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: `${API_BASE}/settings/connections/${encodeURIComponent(connectionName)}`,
      method: 'DELETE'
    });
  }
};

/**
 * Check API connection and WordPress REST API availability
 * @returns {Promise} API response with status
 */
const checkApiConnection = async () => {
  try {
    // Simple test to verify WordPress REST API is available
    const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: '/wp/v2/users/me'
    });
    return {
      success: true,
      message: 'API connection successful',
      user: response
    };
  } catch (error) {
    return handleAPIError(error);
  }
};

/**
 * Error handling utility
 * @param {Error} error - API error
 * @returns {Object} Formatted error response
 */
const handleAPIError = error => {
  // Extract meaningful error message
  let message = 'An unexpected error occurred';
  if (error.message) {
    message = error.message;
  } else if (error.data && error.data.message) {
    message = error.data.message;
  }
  return {
    success: false,
    message,
    error: error
  };
};

/**
 * Default API configuration
 */
const API_CONFIG = {
  base: API_BASE,
  endpoints: {
    settings: `${API_BASE}/settings`,
    connections: `${API_BASE}/settings/connections`
  }
};

/***/ },

/***/ "./src/scripts/dashboard/utils/format-time.js"
/*!****************************************************!*\
  !*** ./src/scripts/dashboard/utils/format-time.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   formatRelativeTime: () => (/* binding */ formatRelativeTime)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);


/**
 * Format timestamp as relative time (e.g., "Just now", "5 minutes ago", "2 hours ago")
 * 
 * Handles multiple input formats:
 * - ISO date strings (e.g., "2024-11-16T10:30:00Z")
 * - Unix timestamps (seconds since epoch)
 * - Date objects
 * 
 * Falls back to locale-formatted date for entries older than 7 days.
 * 
 * @param {string|number|Date} timestamp - Timestamp to format
 * @param {string|null} neverText - Text to show when timestamp is null/undefined (defaults to "Never")
 * @returns {string} Formatted relative time
 * 
 * @example
 * formatRelativeTime('2024-11-16T10:30:00Z') // "5 minutes ago"
 * formatRelativeTime(1700140800) // "2 hours ago"
 * formatRelativeTime(new Date()) // "Just now"
 * formatRelativeTime(null, 'Not available') // "Not available"
 */
const formatRelativeTime = (timestamp, neverText = null) => {
  if (!timestamp) {
    return neverText !== null ? neverText : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Never', 'gregius-data');
  }
  try {
    // Handle different input types
    let date;
    if (typeof timestamp === 'number') {
      // Unix timestamp (seconds since epoch)
      date = new Date(timestamp * 1000);
    } else if (typeof timestamp === 'string') {
      // ISO string or other date string
      date = new Date(timestamp);
    } else {
      // Assume Date object
      date = timestamp;
    }
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    if (diffSecs < 60) {
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Just now', 'gregius-data');
    }
    if (diffMins < 60) {
      return diffMins === 1 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('1 minute ago', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('%d minutes ago', 'gregius-data'), diffMins);
    }
    if (diffHours < 24) {
      return diffHours === 1 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('1 hour ago', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('%d hours ago', 'gregius-data'), diffHours);
    }
    if (diffDays < 7) {
      return diffDays === 1 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('1 day ago', 'gregius-data') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('%d days ago', 'gregius-data'), diffDays);
    }

    // Fall back to formatted date for older entries
    return date.toLocaleString();
  } catch (e) {
    // If parsing fails, return the original value as string
    return String(timestamp);
  }
};

/***/ },

/***/ "./src/styles/dashboard.scss"
/*!***********************************!*\
  !*** ./src/styles/dashboard.scss ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/object-assign/index.js"
/*!*********************************************!*\
  !*** ./node_modules/object-assign/index.js ***!
  \*********************************************/
(module) {

"use strict";
/*
object-assign
(c) Sindre Sorhus
@license MIT
*/


/* eslint-disable no-unused-vars */
var getOwnPropertySymbols = Object.getOwnPropertySymbols;
var hasOwnProperty = Object.prototype.hasOwnProperty;
var propIsEnumerable = Object.prototype.propertyIsEnumerable;

function toObject(val) {
	if (val === null || val === undefined) {
		throw new TypeError('Object.assign cannot be called with null or undefined');
	}

	return Object(val);
}

function shouldUseNative() {
	try {
		if (!Object.assign) {
			return false;
		}

		// Detect buggy property enumeration order in older V8 versions.

		// https://bugs.chromium.org/p/v8/issues/detail?id=4118
		var test1 = new String('abc');  // eslint-disable-line no-new-wrappers
		test1[5] = 'de';
		if (Object.getOwnPropertyNames(test1)[0] === '5') {
			return false;
		}

		// https://bugs.chromium.org/p/v8/issues/detail?id=3056
		var test2 = {};
		for (var i = 0; i < 10; i++) {
			test2['_' + String.fromCharCode(i)] = i;
		}
		var order2 = Object.getOwnPropertyNames(test2).map(function (n) {
			return test2[n];
		});
		if (order2.join('') !== '0123456789') {
			return false;
		}

		// https://bugs.chromium.org/p/v8/issues/detail?id=3056
		var test3 = {};
		'abcdefghijklmnopqrst'.split('').forEach(function (letter) {
			test3[letter] = letter;
		});
		if (Object.keys(Object.assign({}, test3)).join('') !==
				'abcdefghijklmnopqrst') {
			return false;
		}

		return true;
	} catch (err) {
		// We don't expect any of the above to throw, but better to be safe.
		return false;
	}
}

module.exports = shouldUseNative() ? Object.assign : function (target, source) {
	var from;
	var to = toObject(target);
	var symbols;

	for (var s = 1; s < arguments.length; s++) {
		from = Object(arguments[s]);

		for (var key in from) {
			if (hasOwnProperty.call(from, key)) {
				to[key] = from[key];
			}
		}

		if (getOwnPropertySymbols) {
			symbols = getOwnPropertySymbols(from);
			for (var i = 0; i < symbols.length; i++) {
				if (propIsEnumerable.call(from, symbols[i])) {
					to[symbols[i]] = from[symbols[i]];
				}
			}
		}
	}

	return to;
};


/***/ },

/***/ "./node_modules/prop-types/checkPropTypes.js"
/*!***************************************************!*\
  !*** ./node_modules/prop-types/checkPropTypes.js ***!
  \***************************************************/
(module, __unused_webpack_exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var printWarning = function() {};

if (true) {
  var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "./node_modules/prop-types/lib/ReactPropTypesSecret.js");
  var loggedTypeFailures = {};
  var has = __webpack_require__(/*! ./lib/has */ "./node_modules/prop-types/lib/has.js");

  printWarning = function(text) {
    var message = 'Warning: ' + text;
    if (typeof console !== 'undefined') {
      console.error(message);
    }
    try {
      // --- Welcome to debugging React ---
      // This error was thrown as a convenience so that you can use this stack
      // to find the callsite that caused this warning to fire.
      throw new Error(message);
    } catch (x) { /**/ }
  };
}

/**
 * Assert that the values match with the type specs.
 * Error messages are memorized and will only be shown once.
 *
 * @param {object} typeSpecs Map of name to a ReactPropType
 * @param {object} values Runtime values that need to be type-checked
 * @param {string} location e.g. "prop", "context", "child context"
 * @param {string} componentName Name of the component for error messages.
 * @param {?Function} getStack Returns the component stack.
 * @private
 */
function checkPropTypes(typeSpecs, values, location, componentName, getStack) {
  if (true) {
    for (var typeSpecName in typeSpecs) {
      if (has(typeSpecs, typeSpecName)) {
        var error;
        // Prop type validation may throw. In case they do, we don't want to
        // fail the render phase where it didn't fail before. So we log it.
        // After these have been cleaned up, we'll let them throw.
        try {
          // This is intentionally an invariant that gets caught. It's the same
          // behavior as without this statement except with a better message.
          if (typeof typeSpecs[typeSpecName] !== 'function') {
            var err = Error(
              (componentName || 'React class') + ': ' + location + ' type `' + typeSpecName + '` is invalid; ' +
              'it must be a function, usually from the `prop-types` package, but received `' + typeof typeSpecs[typeSpecName] + '`.' +
              'This often happens because of typos such as `PropTypes.function` instead of `PropTypes.func`.'
            );
            err.name = 'Invariant Violation';
            throw err;
          }
          error = typeSpecs[typeSpecName](values, typeSpecName, componentName, location, null, ReactPropTypesSecret);
        } catch (ex) {
          error = ex;
        }
        if (error && !(error instanceof Error)) {
          printWarning(
            (componentName || 'React class') + ': type specification of ' +
            location + ' `' + typeSpecName + '` is invalid; the type checker ' +
            'function must return `null` or an `Error` but returned a ' + typeof error + '. ' +
            'You may have forgotten to pass an argument to the type checker ' +
            'creator (arrayOf, instanceOf, objectOf, oneOf, oneOfType, and ' +
            'shape all require an argument).'
          );
        }
        if (error instanceof Error && !(error.message in loggedTypeFailures)) {
          // Only monitor this failure once because there tends to be a lot of the
          // same error.
          loggedTypeFailures[error.message] = true;

          var stack = getStack ? getStack() : '';

          printWarning(
            'Failed ' + location + ' type: ' + error.message + (stack != null ? stack : '')
          );
        }
      }
    }
  }
}

/**
 * Resets warning cache when testing.
 *
 * @private
 */
checkPropTypes.resetWarningCache = function() {
  if (true) {
    loggedTypeFailures = {};
  }
}

module.exports = checkPropTypes;


/***/ },

/***/ "./node_modules/prop-types/factoryWithTypeCheckers.js"
/*!************************************************************!*\
  !*** ./node_modules/prop-types/factoryWithTypeCheckers.js ***!
  \************************************************************/
(module, __unused_webpack_exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactIs = __webpack_require__(/*! react-is */ "./node_modules/prop-types/node_modules/react-is/index.js");
var assign = __webpack_require__(/*! object-assign */ "./node_modules/object-assign/index.js");

var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "./node_modules/prop-types/lib/ReactPropTypesSecret.js");
var has = __webpack_require__(/*! ./lib/has */ "./node_modules/prop-types/lib/has.js");
var checkPropTypes = __webpack_require__(/*! ./checkPropTypes */ "./node_modules/prop-types/checkPropTypes.js");

var printWarning = function() {};

if (true) {
  printWarning = function(text) {
    var message = 'Warning: ' + text;
    if (typeof console !== 'undefined') {
      console.error(message);
    }
    try {
      // --- Welcome to debugging React ---
      // This error was thrown as a convenience so that you can use this stack
      // to find the callsite that caused this warning to fire.
      throw new Error(message);
    } catch (x) {}
  };
}

function emptyFunctionThatReturnsNull() {
  return null;
}

module.exports = function(isValidElement, throwOnDirectAccess) {
  /* global Symbol */
  var ITERATOR_SYMBOL = typeof Symbol === 'function' && Symbol.iterator;
  var FAUX_ITERATOR_SYMBOL = '@@iterator'; // Before Symbol spec.

  /**
   * Returns the iterator method function contained on the iterable object.
   *
   * Be sure to invoke the function with the iterable as context:
   *
   *     var iteratorFn = getIteratorFn(myIterable);
   *     if (iteratorFn) {
   *       var iterator = iteratorFn.call(myIterable);
   *       ...
   *     }
   *
   * @param {?object} maybeIterable
   * @return {?function}
   */
  function getIteratorFn(maybeIterable) {
    var iteratorFn = maybeIterable && (ITERATOR_SYMBOL && maybeIterable[ITERATOR_SYMBOL] || maybeIterable[FAUX_ITERATOR_SYMBOL]);
    if (typeof iteratorFn === 'function') {
      return iteratorFn;
    }
  }

  /**
   * Collection of methods that allow declaration and validation of props that are
   * supplied to React components. Example usage:
   *
   *   var Props = require('ReactPropTypes');
   *   var MyArticle = React.createClass({
   *     propTypes: {
   *       // An optional string prop named "description".
   *       description: Props.string,
   *
   *       // A required enum prop named "category".
   *       category: Props.oneOf(['News','Photos']).isRequired,
   *
   *       // A prop named "dialog" that requires an instance of Dialog.
   *       dialog: Props.instanceOf(Dialog).isRequired
   *     },
   *     render: function() { ... }
   *   });
   *
   * A more formal specification of how these methods are used:
   *
   *   type := array|bool|func|object|number|string|oneOf([...])|instanceOf(...)
   *   decl := ReactPropTypes.{type}(.isRequired)?
   *
   * Each and every declaration produces a function with the same signature. This
   * allows the creation of custom validation functions. For example:
   *
   *  var MyLink = React.createClass({
   *    propTypes: {
   *      // An optional string or URI prop named "href".
   *      href: function(props, propName, componentName) {
   *        var propValue = props[propName];
   *        if (propValue != null && typeof propValue !== 'string' &&
   *            !(propValue instanceof URI)) {
   *          return new Error(
   *            'Expected a string or an URI for ' + propName + ' in ' +
   *            componentName
   *          );
   *        }
   *      }
   *    },
   *    render: function() {...}
   *  });
   *
   * @internal
   */

  var ANONYMOUS = '<<anonymous>>';

  // Important!
  // Keep this list in sync with production version in `./factoryWithThrowingShims.js`.
  var ReactPropTypes = {
    array: createPrimitiveTypeChecker('array'),
    bigint: createPrimitiveTypeChecker('bigint'),
    bool: createPrimitiveTypeChecker('boolean'),
    func: createPrimitiveTypeChecker('function'),
    number: createPrimitiveTypeChecker('number'),
    object: createPrimitiveTypeChecker('object'),
    string: createPrimitiveTypeChecker('string'),
    symbol: createPrimitiveTypeChecker('symbol'),

    any: createAnyTypeChecker(),
    arrayOf: createArrayOfTypeChecker,
    element: createElementTypeChecker(),
    elementType: createElementTypeTypeChecker(),
    instanceOf: createInstanceTypeChecker,
    node: createNodeChecker(),
    objectOf: createObjectOfTypeChecker,
    oneOf: createEnumTypeChecker,
    oneOfType: createUnionTypeChecker,
    shape: createShapeTypeChecker,
    exact: createStrictShapeTypeChecker,
  };

  /**
   * inlined Object.is polyfill to avoid requiring consumers ship their own
   * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/is
   */
  /*eslint-disable no-self-compare*/
  function is(x, y) {
    // SameValue algorithm
    if (x === y) {
      // Steps 1-5, 7-10
      // Steps 6.b-6.e: +0 != -0
      return x !== 0 || 1 / x === 1 / y;
    } else {
      // Step 6.a: NaN == NaN
      return x !== x && y !== y;
    }
  }
  /*eslint-enable no-self-compare*/

  /**
   * We use an Error-like object for backward compatibility as people may call
   * PropTypes directly and inspect their output. However, we don't use real
   * Errors anymore. We don't inspect their stack anyway, and creating them
   * is prohibitively expensive if they are created too often, such as what
   * happens in oneOfType() for any type before the one that matched.
   */
  function PropTypeError(message, data) {
    this.message = message;
    this.data = data && typeof data === 'object' ? data: {};
    this.stack = '';
  }
  // Make `instanceof Error` still work for returned errors.
  PropTypeError.prototype = Error.prototype;

  function createChainableTypeChecker(validate) {
    if (true) {
      var manualPropTypeCallCache = {};
      var manualPropTypeWarningCount = 0;
    }
    function checkType(isRequired, props, propName, componentName, location, propFullName, secret) {
      componentName = componentName || ANONYMOUS;
      propFullName = propFullName || propName;

      if (secret !== ReactPropTypesSecret) {
        if (throwOnDirectAccess) {
          // New behavior only for users of `prop-types` package
          var err = new Error(
            'Calling PropTypes validators directly is not supported by the `prop-types` package. ' +
            'Use `PropTypes.checkPropTypes()` to call them. ' +
            'Read more at http://fb.me/use-check-prop-types'
          );
          err.name = 'Invariant Violation';
          throw err;
        } else if ( true && typeof console !== 'undefined') {
          // Old behavior for people using React.PropTypes
          var cacheKey = componentName + ':' + propName;
          if (
            !manualPropTypeCallCache[cacheKey] &&
            // Avoid spamming the console because they are often not actionable except for lib authors
            manualPropTypeWarningCount < 3
          ) {
            printWarning(
              'You are manually calling a React.PropTypes validation ' +
              'function for the `' + propFullName + '` prop on `' + componentName + '`. This is deprecated ' +
              'and will throw in the standalone `prop-types` package. ' +
              'You may be seeing this warning due to a third-party PropTypes ' +
              'library. See https://fb.me/react-warning-dont-call-proptypes ' + 'for details.'
            );
            manualPropTypeCallCache[cacheKey] = true;
            manualPropTypeWarningCount++;
          }
        }
      }
      if (props[propName] == null) {
        if (isRequired) {
          if (props[propName] === null) {
            return new PropTypeError('The ' + location + ' `' + propFullName + '` is marked as required ' + ('in `' + componentName + '`, but its value is `null`.'));
          }
          return new PropTypeError('The ' + location + ' `' + propFullName + '` is marked as required in ' + ('`' + componentName + '`, but its value is `undefined`.'));
        }
        return null;
      } else {
        return validate(props, propName, componentName, location, propFullName);
      }
    }

    var chainedCheckType = checkType.bind(null, false);
    chainedCheckType.isRequired = checkType.bind(null, true);

    return chainedCheckType;
  }

  function createPrimitiveTypeChecker(expectedType) {
    function validate(props, propName, componentName, location, propFullName, secret) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== expectedType) {
        // `propValue` being instance of, say, date/regexp, pass the 'object'
        // check, but we can offer a more precise error message here rather than
        // 'of type `object`'.
        var preciseType = getPreciseType(propValue);

        return new PropTypeError(
          'Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + preciseType + '` supplied to `' + componentName + '`, expected ') + ('`' + expectedType + '`.'),
          {expectedType: expectedType}
        );
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createAnyTypeChecker() {
    return createChainableTypeChecker(emptyFunctionThatReturnsNull);
  }

  function createArrayOfTypeChecker(typeChecker) {
    function validate(props, propName, componentName, location, propFullName) {
      if (typeof typeChecker !== 'function') {
        return new PropTypeError('Property `' + propFullName + '` of component `' + componentName + '` has invalid PropType notation inside arrayOf.');
      }
      var propValue = props[propName];
      if (!Array.isArray(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected an array.'));
      }
      for (var i = 0; i < propValue.length; i++) {
        var error = typeChecker(propValue, i, componentName, location, propFullName + '[' + i + ']', ReactPropTypesSecret);
        if (error instanceof Error) {
          return error;
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createElementTypeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      if (!isValidElement(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected a single ReactElement.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createElementTypeTypeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      if (!ReactIs.isValidElementType(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected a single ReactElement type.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createInstanceTypeChecker(expectedClass) {
    function validate(props, propName, componentName, location, propFullName) {
      if (!(props[propName] instanceof expectedClass)) {
        var expectedClassName = expectedClass.name || ANONYMOUS;
        var actualClassName = getClassName(props[propName]);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + actualClassName + '` supplied to `' + componentName + '`, expected ') + ('instance of `' + expectedClassName + '`.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createEnumTypeChecker(expectedValues) {
    if (!Array.isArray(expectedValues)) {
      if (true) {
        if (arguments.length > 1) {
          printWarning(
            'Invalid arguments supplied to oneOf, expected an array, got ' + arguments.length + ' arguments. ' +
            'A common mistake is to write oneOf(x, y, z) instead of oneOf([x, y, z]).'
          );
        } else {
          printWarning('Invalid argument supplied to oneOf, expected an array.');
        }
      }
      return emptyFunctionThatReturnsNull;
    }

    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      for (var i = 0; i < expectedValues.length; i++) {
        if (is(propValue, expectedValues[i])) {
          return null;
        }
      }

      var valuesString = JSON.stringify(expectedValues, function replacer(key, value) {
        var type = getPreciseType(value);
        if (type === 'symbol') {
          return String(value);
        }
        return value;
      });
      return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of value `' + String(propValue) + '` ' + ('supplied to `' + componentName + '`, expected one of ' + valuesString + '.'));
    }
    return createChainableTypeChecker(validate);
  }

  function createObjectOfTypeChecker(typeChecker) {
    function validate(props, propName, componentName, location, propFullName) {
      if (typeof typeChecker !== 'function') {
        return new PropTypeError('Property `' + propFullName + '` of component `' + componentName + '` has invalid PropType notation inside objectOf.');
      }
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected an object.'));
      }
      for (var key in propValue) {
        if (has(propValue, key)) {
          var error = typeChecker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
          if (error instanceof Error) {
            return error;
          }
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createUnionTypeChecker(arrayOfTypeCheckers) {
    if (!Array.isArray(arrayOfTypeCheckers)) {
       true ? printWarning('Invalid argument supplied to oneOfType, expected an instance of array.') : 0;
      return emptyFunctionThatReturnsNull;
    }

    for (var i = 0; i < arrayOfTypeCheckers.length; i++) {
      var checker = arrayOfTypeCheckers[i];
      if (typeof checker !== 'function') {
        printWarning(
          'Invalid argument supplied to oneOfType. Expected an array of check functions, but ' +
          'received ' + getPostfixForTypeWarning(checker) + ' at index ' + i + '.'
        );
        return emptyFunctionThatReturnsNull;
      }
    }

    function validate(props, propName, componentName, location, propFullName) {
      var expectedTypes = [];
      for (var i = 0; i < arrayOfTypeCheckers.length; i++) {
        var checker = arrayOfTypeCheckers[i];
        var checkerResult = checker(props, propName, componentName, location, propFullName, ReactPropTypesSecret);
        if (checkerResult == null) {
          return null;
        }
        if (checkerResult.data && has(checkerResult.data, 'expectedType')) {
          expectedTypes.push(checkerResult.data.expectedType);
        }
      }
      var expectedTypesMessage = (expectedTypes.length > 0) ? ', expected one of type [' + expectedTypes.join(', ') + ']': '';
      return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` supplied to ' + ('`' + componentName + '`' + expectedTypesMessage + '.'));
    }
    return createChainableTypeChecker(validate);
  }

  function createNodeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      if (!isNode(props[propName])) {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` supplied to ' + ('`' + componentName + '`, expected a ReactNode.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function invalidValidatorError(componentName, location, propFullName, key, type) {
    return new PropTypeError(
      (componentName || 'React class') + ': ' + location + ' type `' + propFullName + '.' + key + '` is invalid; ' +
      'it must be a function, usually from the `prop-types` package, but received `' + type + '`.'
    );
  }

  function createShapeTypeChecker(shapeTypes) {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type `' + propType + '` ' + ('supplied to `' + componentName + '`, expected `object`.'));
      }
      for (var key in shapeTypes) {
        var checker = shapeTypes[key];
        if (typeof checker !== 'function') {
          return invalidValidatorError(componentName, location, propFullName, key, getPreciseType(checker));
        }
        var error = checker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
        if (error) {
          return error;
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createStrictShapeTypeChecker(shapeTypes) {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type `' + propType + '` ' + ('supplied to `' + componentName + '`, expected `object`.'));
      }
      // We need to check all keys in case some are required but missing from props.
      var allKeys = assign({}, props[propName], shapeTypes);
      for (var key in allKeys) {
        var checker = shapeTypes[key];
        if (has(shapeTypes, key) && typeof checker !== 'function') {
          return invalidValidatorError(componentName, location, propFullName, key, getPreciseType(checker));
        }
        if (!checker) {
          return new PropTypeError(
            'Invalid ' + location + ' `' + propFullName + '` key `' + key + '` supplied to `' + componentName + '`.' +
            '\nBad object: ' + JSON.stringify(props[propName], null, '  ') +
            '\nValid keys: ' + JSON.stringify(Object.keys(shapeTypes), null, '  ')
          );
        }
        var error = checker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
        if (error) {
          return error;
        }
      }
      return null;
    }

    return createChainableTypeChecker(validate);
  }

  function isNode(propValue) {
    switch (typeof propValue) {
      case 'number':
      case 'string':
      case 'undefined':
        return true;
      case 'boolean':
        return !propValue;
      case 'object':
        if (Array.isArray(propValue)) {
          return propValue.every(isNode);
        }
        if (propValue === null || isValidElement(propValue)) {
          return true;
        }

        var iteratorFn = getIteratorFn(propValue);
        if (iteratorFn) {
          var iterator = iteratorFn.call(propValue);
          var step;
          if (iteratorFn !== propValue.entries) {
            while (!(step = iterator.next()).done) {
              if (!isNode(step.value)) {
                return false;
              }
            }
          } else {
            // Iterator will provide entry [k,v] tuples rather than values.
            while (!(step = iterator.next()).done) {
              var entry = step.value;
              if (entry) {
                if (!isNode(entry[1])) {
                  return false;
                }
              }
            }
          }
        } else {
          return false;
        }

        return true;
      default:
        return false;
    }
  }

  function isSymbol(propType, propValue) {
    // Native Symbol.
    if (propType === 'symbol') {
      return true;
    }

    // falsy value can't be a Symbol
    if (!propValue) {
      return false;
    }

    // 19.4.3.5 Symbol.prototype[@@toStringTag] === 'Symbol'
    if (propValue['@@toStringTag'] === 'Symbol') {
      return true;
    }

    // Fallback for non-spec compliant Symbols which are polyfilled.
    if (typeof Symbol === 'function' && propValue instanceof Symbol) {
      return true;
    }

    return false;
  }

  // Equivalent of `typeof` but with special handling for array and regexp.
  function getPropType(propValue) {
    var propType = typeof propValue;
    if (Array.isArray(propValue)) {
      return 'array';
    }
    if (propValue instanceof RegExp) {
      // Old webkits (at least until Android 4.0) return 'function' rather than
      // 'object' for typeof a RegExp. We'll normalize this here so that /bla/
      // passes PropTypes.object.
      return 'object';
    }
    if (isSymbol(propType, propValue)) {
      return 'symbol';
    }
    return propType;
  }

  // This handles more types than `getPropType`. Only used for error messages.
  // See `createPrimitiveTypeChecker`.
  function getPreciseType(propValue) {
    if (typeof propValue === 'undefined' || propValue === null) {
      return '' + propValue;
    }
    var propType = getPropType(propValue);
    if (propType === 'object') {
      if (propValue instanceof Date) {
        return 'date';
      } else if (propValue instanceof RegExp) {
        return 'regexp';
      }
    }
    return propType;
  }

  // Returns a string that is postfixed to a warning about an invalid type.
  // For example, "undefined" or "of type array"
  function getPostfixForTypeWarning(value) {
    var type = getPreciseType(value);
    switch (type) {
      case 'array':
      case 'object':
        return 'an ' + type;
      case 'boolean':
      case 'date':
      case 'regexp':
        return 'a ' + type;
      default:
        return type;
    }
  }

  // Returns class name of the object, if any.
  function getClassName(propValue) {
    if (!propValue.constructor || !propValue.constructor.name) {
      return ANONYMOUS;
    }
    return propValue.constructor.name;
  }

  ReactPropTypes.checkPropTypes = checkPropTypes;
  ReactPropTypes.resetWarningCache = checkPropTypes.resetWarningCache;
  ReactPropTypes.PropTypes = ReactPropTypes;

  return ReactPropTypes;
};


/***/ },

/***/ "./node_modules/prop-types/index.js"
/*!******************************************!*\
  !*** ./node_modules/prop-types/index.js ***!
  \******************************************/
(module, __unused_webpack_exports, __webpack_require__) {

/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

if (true) {
  var ReactIs = __webpack_require__(/*! react-is */ "./node_modules/prop-types/node_modules/react-is/index.js");

  // By explicitly using `prop-types` you are opting into new development behavior.
  // http://fb.me/prop-types-in-prod
  var throwOnDirectAccess = true;
  module.exports = __webpack_require__(/*! ./factoryWithTypeCheckers */ "./node_modules/prop-types/factoryWithTypeCheckers.js")(ReactIs.isElement, throwOnDirectAccess);
} else // removed by dead control flow
{}


/***/ },

/***/ "./node_modules/prop-types/lib/ReactPropTypesSecret.js"
/*!*************************************************************!*\
  !*** ./node_modules/prop-types/lib/ReactPropTypesSecret.js ***!
  \*************************************************************/
(module) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactPropTypesSecret = 'SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED';

module.exports = ReactPropTypesSecret;


/***/ },

/***/ "./node_modules/prop-types/lib/has.js"
/*!********************************************!*\
  !*** ./node_modules/prop-types/lib/has.js ***!
  \********************************************/
(module) {

module.exports = Function.call.bind(Object.prototype.hasOwnProperty);


/***/ },

/***/ "./node_modules/prop-types/node_modules/react-is/cjs/react-is.development.js"
/*!***********************************************************************************!*\
  !*** ./node_modules/prop-types/node_modules/react-is/cjs/react-is.development.js ***!
  \***********************************************************************************/
(__unused_webpack_module, exports) {

"use strict";
/** @license React v16.13.1
 * react-is.development.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */





if (true) {
  (function() {
'use strict';

// The Symbol used to tag the ReactElement-like types. If there is no native Symbol
// nor polyfill, then a plain number is used for performance.
var hasSymbol = typeof Symbol === 'function' && Symbol.for;
var REACT_ELEMENT_TYPE = hasSymbol ? Symbol.for('react.element') : 0xeac7;
var REACT_PORTAL_TYPE = hasSymbol ? Symbol.for('react.portal') : 0xeaca;
var REACT_FRAGMENT_TYPE = hasSymbol ? Symbol.for('react.fragment') : 0xeacb;
var REACT_STRICT_MODE_TYPE = hasSymbol ? Symbol.for('react.strict_mode') : 0xeacc;
var REACT_PROFILER_TYPE = hasSymbol ? Symbol.for('react.profiler') : 0xead2;
var REACT_PROVIDER_TYPE = hasSymbol ? Symbol.for('react.provider') : 0xeacd;
var REACT_CONTEXT_TYPE = hasSymbol ? Symbol.for('react.context') : 0xeace; // TODO: We don't use AsyncMode or ConcurrentMode anymore. They were temporary
// (unstable) APIs that have been removed. Can we remove the symbols?

var REACT_ASYNC_MODE_TYPE = hasSymbol ? Symbol.for('react.async_mode') : 0xeacf;
var REACT_CONCURRENT_MODE_TYPE = hasSymbol ? Symbol.for('react.concurrent_mode') : 0xeacf;
var REACT_FORWARD_REF_TYPE = hasSymbol ? Symbol.for('react.forward_ref') : 0xead0;
var REACT_SUSPENSE_TYPE = hasSymbol ? Symbol.for('react.suspense') : 0xead1;
var REACT_SUSPENSE_LIST_TYPE = hasSymbol ? Symbol.for('react.suspense_list') : 0xead8;
var REACT_MEMO_TYPE = hasSymbol ? Symbol.for('react.memo') : 0xead3;
var REACT_LAZY_TYPE = hasSymbol ? Symbol.for('react.lazy') : 0xead4;
var REACT_BLOCK_TYPE = hasSymbol ? Symbol.for('react.block') : 0xead9;
var REACT_FUNDAMENTAL_TYPE = hasSymbol ? Symbol.for('react.fundamental') : 0xead5;
var REACT_RESPONDER_TYPE = hasSymbol ? Symbol.for('react.responder') : 0xead6;
var REACT_SCOPE_TYPE = hasSymbol ? Symbol.for('react.scope') : 0xead7;

function isValidElementType(type) {
  return typeof type === 'string' || typeof type === 'function' || // Note: its typeof might be other than 'symbol' or 'number' if it's a polyfill.
  type === REACT_FRAGMENT_TYPE || type === REACT_CONCURRENT_MODE_TYPE || type === REACT_PROFILER_TYPE || type === REACT_STRICT_MODE_TYPE || type === REACT_SUSPENSE_TYPE || type === REACT_SUSPENSE_LIST_TYPE || typeof type === 'object' && type !== null && (type.$$typeof === REACT_LAZY_TYPE || type.$$typeof === REACT_MEMO_TYPE || type.$$typeof === REACT_PROVIDER_TYPE || type.$$typeof === REACT_CONTEXT_TYPE || type.$$typeof === REACT_FORWARD_REF_TYPE || type.$$typeof === REACT_FUNDAMENTAL_TYPE || type.$$typeof === REACT_RESPONDER_TYPE || type.$$typeof === REACT_SCOPE_TYPE || type.$$typeof === REACT_BLOCK_TYPE);
}

function typeOf(object) {
  if (typeof object === 'object' && object !== null) {
    var $$typeof = object.$$typeof;

    switch ($$typeof) {
      case REACT_ELEMENT_TYPE:
        var type = object.type;

        switch (type) {
          case REACT_ASYNC_MODE_TYPE:
          case REACT_CONCURRENT_MODE_TYPE:
          case REACT_FRAGMENT_TYPE:
          case REACT_PROFILER_TYPE:
          case REACT_STRICT_MODE_TYPE:
          case REACT_SUSPENSE_TYPE:
            return type;

          default:
            var $$typeofType = type && type.$$typeof;

            switch ($$typeofType) {
              case REACT_CONTEXT_TYPE:
              case REACT_FORWARD_REF_TYPE:
              case REACT_LAZY_TYPE:
              case REACT_MEMO_TYPE:
              case REACT_PROVIDER_TYPE:
                return $$typeofType;

              default:
                return $$typeof;
            }

        }

      case REACT_PORTAL_TYPE:
        return $$typeof;
    }
  }

  return undefined;
} // AsyncMode is deprecated along with isAsyncMode

var AsyncMode = REACT_ASYNC_MODE_TYPE;
var ConcurrentMode = REACT_CONCURRENT_MODE_TYPE;
var ContextConsumer = REACT_CONTEXT_TYPE;
var ContextProvider = REACT_PROVIDER_TYPE;
var Element = REACT_ELEMENT_TYPE;
var ForwardRef = REACT_FORWARD_REF_TYPE;
var Fragment = REACT_FRAGMENT_TYPE;
var Lazy = REACT_LAZY_TYPE;
var Memo = REACT_MEMO_TYPE;
var Portal = REACT_PORTAL_TYPE;
var Profiler = REACT_PROFILER_TYPE;
var StrictMode = REACT_STRICT_MODE_TYPE;
var Suspense = REACT_SUSPENSE_TYPE;
var hasWarnedAboutDeprecatedIsAsyncMode = false; // AsyncMode should be deprecated

function isAsyncMode(object) {
  {
    if (!hasWarnedAboutDeprecatedIsAsyncMode) {
      hasWarnedAboutDeprecatedIsAsyncMode = true; // Using console['warn'] to evade Babel and ESLint

      console['warn']('The ReactIs.isAsyncMode() alias has been deprecated, ' + 'and will be removed in React 17+. Update your code to use ' + 'ReactIs.isConcurrentMode() instead. It has the exact same API.');
    }
  }

  return isConcurrentMode(object) || typeOf(object) === REACT_ASYNC_MODE_TYPE;
}
function isConcurrentMode(object) {
  return typeOf(object) === REACT_CONCURRENT_MODE_TYPE;
}
function isContextConsumer(object) {
  return typeOf(object) === REACT_CONTEXT_TYPE;
}
function isContextProvider(object) {
  return typeOf(object) === REACT_PROVIDER_TYPE;
}
function isElement(object) {
  return typeof object === 'object' && object !== null && object.$$typeof === REACT_ELEMENT_TYPE;
}
function isForwardRef(object) {
  return typeOf(object) === REACT_FORWARD_REF_TYPE;
}
function isFragment(object) {
  return typeOf(object) === REACT_FRAGMENT_TYPE;
}
function isLazy(object) {
  return typeOf(object) === REACT_LAZY_TYPE;
}
function isMemo(object) {
  return typeOf(object) === REACT_MEMO_TYPE;
}
function isPortal(object) {
  return typeOf(object) === REACT_PORTAL_TYPE;
}
function isProfiler(object) {
  return typeOf(object) === REACT_PROFILER_TYPE;
}
function isStrictMode(object) {
  return typeOf(object) === REACT_STRICT_MODE_TYPE;
}
function isSuspense(object) {
  return typeOf(object) === REACT_SUSPENSE_TYPE;
}

exports.AsyncMode = AsyncMode;
exports.ConcurrentMode = ConcurrentMode;
exports.ContextConsumer = ContextConsumer;
exports.ContextProvider = ContextProvider;
exports.Element = Element;
exports.ForwardRef = ForwardRef;
exports.Fragment = Fragment;
exports.Lazy = Lazy;
exports.Memo = Memo;
exports.Portal = Portal;
exports.Profiler = Profiler;
exports.StrictMode = StrictMode;
exports.Suspense = Suspense;
exports.isAsyncMode = isAsyncMode;
exports.isConcurrentMode = isConcurrentMode;
exports.isContextConsumer = isContextConsumer;
exports.isContextProvider = isContextProvider;
exports.isElement = isElement;
exports.isForwardRef = isForwardRef;
exports.isFragment = isFragment;
exports.isLazy = isLazy;
exports.isMemo = isMemo;
exports.isPortal = isPortal;
exports.isProfiler = isProfiler;
exports.isStrictMode = isStrictMode;
exports.isSuspense = isSuspense;
exports.isValidElementType = isValidElementType;
exports.typeOf = typeOf;
  })();
}


/***/ },

/***/ "./node_modules/prop-types/node_modules/react-is/index.js"
/*!****************************************************************!*\
  !*** ./node_modules/prop-types/node_modules/react-is/index.js ***!
  \****************************************************************/
(module, __unused_webpack_exports, __webpack_require__) {

"use strict";


if (false) // removed by dead control flow
{} else {
  module.exports = __webpack_require__(/*! ./cjs/react-is.development.js */ "./node_modules/prop-types/node_modules/react-is/cjs/react-is.development.js");
}


/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

"use strict";
module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

"use strict";
module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

"use strict";
module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

"use strict";
module.exports = window["wp"]["data"];

/***/ },

/***/ "@wordpress/dom-ready"
/*!**********************************!*\
  !*** external ["wp","domReady"] ***!
  \**********************************/
(module) {

"use strict";
module.exports = window["wp"]["domReady"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

"use strict";
module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

"use strict";
module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

"use strict";
module.exports = window["wp"]["primitives"];

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/check.mjs"
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/check.mjs ***!
  \**********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ check_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/check.tsx


var check_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M16.5 7.5 10 13.9l-2.5-2.4-1 1 3.5 3.6 7.5-7.6z" }) });

//# sourceMappingURL=check.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/copy.mjs"
/*!*********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/copy.mjs ***!
  \*********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ copy_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/copy.tsx


var copy_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { fillRule: "evenodd", clipRule: "evenodd", d: "M5 4.5h11a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5ZM3 5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm17 3v10.75c0 .69-.56 1.25-1.25 1.25H6v1.5h12.75a2.75 2.75 0 0 0 2.75-2.75V8H20Z" }) });

//# sourceMappingURL=copy.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs"
/*!******************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/more-vertical.mjs ***!
  \******************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ more_vertical_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/more-vertical.tsx


var more_vertical_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M13 19h-2v-2h2v2zm0-6h-2v-2h2v2zm0-6h-2V5h2v2z" }) });

//# sourceMappingURL=more-vertical.mjs.map


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!**********************************!*\
  !*** ./src/scripts/dashboard.js ***!
  \**********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _dashboard_components_App__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./dashboard/components/App */ "./src/scripts/dashboard/components/App.js");
/* harmony import */ var _styles_dashboard_scss__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../styles/dashboard.scss */ "./src/styles/dashboard.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Gregius PostgreSQL Dashboard - React Entry Point
 * 
 * Modern React-based admin dashboard for the Gregius PostgreSQL plugin.
 * Integrates with the REST API endpoints for real-time settings management.
 */






// Import the main React app component


// Import styles


/**
 * Initialize the React dashboard when DOM is ready
 */

_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2___default()(() => {
  // Configure API fetch if WordPress settings are available
  if (window.wpApiSettings) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().createNonceMiddleware(window.wpApiSettings.nonce));
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().createRootURLMiddleware(window.wpApiSettings.root));
  } else if (window.ggPgDashboard && window.ggPgDashboard.nonce) {
    // Fallback to our own configuration
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().createNonceMiddleware(window.ggPgDashboard.nonce));
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default().createRootURLMiddleware(window.ggPgDashboard.restUrl));
  }
  const dashboardContainer = document.getElementById('gg-data-react-dashboard');
  if (dashboardContainer) {
    // Set flag to indicate React dashboard is active
    window.ggPgReactActive = true;
    try {
      // Render the React app
      (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_dashboard_components_App__WEBPACK_IMPORTED_MODULE_4__["default"], {}), dashboardContainer);
    } catch (error) {
      console.error('❌ Error rendering React App:', error);
      console.error('Error stack:', error.stack);

      // Signal React load failure
      if (window.jQuery) {
        window.jQuery(document).trigger('gg-data-react-failed');
      }
    }
  } else {
    // Fallback: Create container if it doesn't exist
    const adminPage = document.querySelector('.wrap.gg-data-admin');
    if (adminPage) {
      const reactContainer = document.createElement('div');
      reactContainer.id = 'gg-data-react-dashboard';
      adminPage.appendChild(reactContainer);
      try {
        (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_dashboard_components_App__WEBPACK_IMPORTED_MODULE_4__["default"], {}), reactContainer);
      } catch (error) {
        console.error('❌ Error rendering React App in fallback:', error);
        console.error('Error stack:', error.stack);

        // Signal React load failure
        if (window.jQuery) {
          window.jQuery(document).trigger('gg-data-react-failed');
        }
      }
    } else {
      console.error('❌ Could not find admin page container');
      // Dashboard container not found - React dashboard not initialized
    }
  }
});

// Make REST API configuration globally available
window.ggPgDashboard = {
  apiUrl: wpApiSettings?.root + 'gg-data/v1/',
  nonce: wpApiSettings?.nonce,
  currentUser: wpApiSettings?.currentUser || null,
  adminUrl: window.location.href,
  pluginUrl: window.location.origin + '/wp-content/plugins/gregius-data/'
};
})();

/******/ })()
;
//# sourceMappingURL=dashboard.min.228816f3c8ce651e2e7b.js.map