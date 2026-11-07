// Create chart
ew.createChart = function (args) {
    let canvas = document.getElementById(args.canvasId),
        config = args.chartJson,
        showPercentage = args.showPercentage,
        yFieldFormat = args.yFieldFormat,
        yAxisFormat = args.yAxisFormat,
        formatNumber = (value, format) => {
            if (format == "Currency")
                return ew.formatCurrency(value, ew.CURRENCY_FORMAT);
            else if (format == "Number")
                return ew.formatNumber(value, ew.NUMBER_FORMAT);
            else if (format == "Percent")
                return ew.formatPercent(value, ew.PERCENT_FORMAT);
            return value;
        },
        getDefaultColor = () => ew.getPreferredTheme() == "dark" ? "#fff" : "#666",
        getDefaultBorderColor = () => ew.getPreferredTheme() == "dark" ? "rgba(255, 255, 255, 0.2)" : "rgba(0, 0, 0, 0.1)",
        setColor = (obj, value) => {
            if (typeof obj?.color != "undefined")
                obj.color = value;
        },
        setColors = (chart) => {
            const borderColor = getDefaultBorderColor();
            const color = getDefaultColor();
            setColor(chart.options.plugins.title, color);
            setColor(chart.options.plugins.subtitle, color);
            setColor(chart.options.plugins.datalabels, color);
            setColor(chart.options.plugins.legend?.labels, color);
            setColor(chart.options.plugins.legend?.title, color);
            setColor(chart.options.scales.x?.ticks, color);
            setColor(chart.options.scales.x?.grid, borderColor);
            setColor(chart.options.scales.y?.ticks, color);
            setColor(chart.options.scales.y?.grid, borderColor);
            setColor(chart.options.scales.r?.angleLines, borderColor);
            setColor(chart.options.scales.r?.grid, borderColor);
            setColor(chart.options.scales.r?.pointLabels, color);
            setColor(chart.options.scales.r?.ticks, color);
            // if (chart.options.plugins.annotation?.annotations) {
            //     let values = Object.values(chart.options.plugins.annotation.annotations);
            //     for (let value of values) {
            //         if (value.type == "line") {
            //         }
            //     }
            // }
        };
    canvas.dir = "ltr"; // Keep it LTR so currency symbol position in the format pattern will not be changed
    if (config.data && config.data.datasets.length > 0) {
        config.options.onHover = function (e) {
            let el = this.getElementsAtEventForMode(e.native, "nearest", { intersect: true }, false);
            e.native.target.style.cursor = (el.length) ? "pointer" : "default";
        };
        let axis = config.options.indexAxis == "y" ? "x" : "y";
        if (!["pie", "doughnut", "polarArea", "radar"].includes(config.type)) { // Format x/y axis for non pie/doughnut/polarArea/radar charts
            // Format Primary Axis (x/y)
            config.options.scales[axis] = ew.deepAssign({}, config.options.scales[axis], {
                ticks: {
                    callback: (value, index, values) => formatNumber(value, yAxisFormat.length ? yAxisFormat[0] : "")
                }
            });
            // Format Secondary Axis (y1)
            if (config.options.scales["y1"]) {
                config = ew.deepAssign({}, config, {
                    options: {
                        scales: {
                            y1: {
                                ticks: {
                                    callback: (value, index, values) => formatNumber(value, yAxisFormat.length > 1 ? yAxisFormat[1] : "")
                                }
                            }
                        }
                    }
                });
            }
        }
        config = ew.deepAssign({}, config, {
            plugins: [ChartDataLabels],
            options: {
                plugins: {
                    datalabels: {
                        align: ["line", "area"].includes(config.type) ? "top" : ew.IS_RTL ? "right" : "center",
                        rotation: (context) => {
                            return (context.dataset.type || config.type) == "bar" && config.options.indexAxis != "y" ? -90 : 0; // Rotate label -90 degrees for column chart
                        },
                        formatter: (value, context) => {
                            let format = yFieldFormat.length > context.datasetIndex ? yFieldFormat[context.datasetIndex] : (yFieldFormat.length > 0 ? yFieldFormat[0] : "");
                            if (["pie", "doughnut"].includes(config.type) && showPercentage) { // Show as percentage
                                let sum = context.dataset.data.reduce((accum, val) => accum + val);
                                value = value / sum;
                                format = "Percent";
                            } else if (config.options.plugins.stacked100.enable) {
                                const data = context.chart.data;
                                const { datasetIndex, dataIndex } = context;
                                return `${data.calculatedData[datasetIndex][dataIndex]}%`; // Return percent
                                //return `${data.calculatedData[datasetIndex][dataIndex]}% (${data.originalData[datasetIndex][dataIndex]})`; // Return percent and value
                            }
                            return formatNumber(value, format);
                        },
                        color: getDefaultColor()
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                let label = ["pie", "doughnut"].includes(config.type) ? context.label || "" : "",
                                    value = context.raw,
                                    format = yFieldFormat.length > context.datasetIndex ? yFieldFormat[context.datasetIndex] : (yFieldFormat.length > 0 ? yFieldFormat[0] : "");
                                if (label)
                                    label += ": ";
                                if (["pie", "doughnut"].includes(config.type) && showPercentage) {
                                    let sum = context.dataset.data.reduce((accum, val) => {
                                        return accum + val;
                                    });
                                    value = value / sum;
                                    format = "Percent";
                                }
                                label += formatNumber(value, format);
                                return label;
                            }
                        }
                    }
                }
            }
        }, ew.chartConfig, ew.charts[args.id]); // Deep copy (chart config + global config + user chart config)
        let evtArgs = { id: args.id, ctx: canvas, config: config };
        window.jQuery?.(document).trigger("chart", [evtArgs]);
        Chart.register(ChartjsPluginStacked100.default); // Register plugin stacked100
        let chart = new Chart(evtArgs.ctx, evtArgs.config);
        setColors(chart);
        if (ew.DEBUG)
            console.log(evtArgs.config);
        evtArgs.ctx.addEventListener("click", (e) => {
            let activePoints = chart.getElementsAtEventForMode(e, "index", { intersect: true }, false);
            if (activePoints[0]) {
                let activePoint = activePoints[0],
                    links = chart.data.datasets[activePoint.datasetIndex].links,
                    link = Array.isArray(links) ? links[activePoint.index] : {};
                if (args.useDrilldownPanel) {
                    ew.showDrillDown(null, canvas, link.url, link.id, link.hdr);
                } else if (args.useDrilldownPanel === false) { // If null, no drilldown
                    return ew.redirect(link.url);
                }
            }
        });
        document.addEventListener("changetheme", (e) => {
            setColors(chart, e.detail);
            chart.update();
        });
        window.exportCharts["chart_" + args.id] = chart; // Export chart
    } else {
        canvas.classList.add("d-none");
    }
}