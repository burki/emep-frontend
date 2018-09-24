Highcharts.theme = {
    colors: ['#1869A3', '#02A5BE', '#484848', '#B8D4E3', '#00ffff', '#00ffff',
        '#00ffff', '#00ffff', '#00ffff'],
    chart: {
        backgroundColor: 'transparent',
    },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: false
            },
            showInLegend: true
        }
    },
    title: {
        align: 'left',
        style: {
            color: '#000',
            font: 'bold 16px "Lato", Verdana, sans-serif'
        }
    },
    subtitle: {
        style: {
            color: '#7C8790',
            font: '8px "Lato"'
        },
        y: 385
    },

    legend: {
        itemStyle: {
            font: '10px "Lato", Verdana, sans-serif',
            color: '#5B6974'
        },
        itemHoverStyle:{
            color: '#7C8790'
        }
    },
    xAxis: {
        labels: {
            rotation: 0,
            style: {
                color: '#7C8790',
                font: '9px "Lato", Verdana, sans-serif'
            }
        }
    },
    yAxis: {
        labels: {
            rotation: 0,
            style: {
                color: '#7C8790',
                font: '9px "Lato", Verdana, sans-serif'
            }
        }
    }
};

// Apply the theme
Highcharts.setOptions(Highcharts.theme);

