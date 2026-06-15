const GregiusLogo = ({ className = 'gg-data-logo-clean' }) => (
    <svg
        className={className}
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 300 300"
        width="28"
        height="28"
        aria-hidden="true"
        focusable="false"
    >
        <defs>
            <linearGradient id="ggDataCleanLogoGradient" x1="28" y1="28" x2="272" y2="272" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stopColor="#FF0000">
                    <animate
                        attributeName="stop-color"
                        values="#FF0000;#FF7F00;#FFFF00;#FF0000"
                        dur="16s"
                        repeatCount="indefinite"
                    />
                </stop>
                <stop offset="45%" stopColor="#00FF00">
                    <animate
                        attributeName="stop-color"
                        values="#00FF00;#0000FF;#4B0082;#00FF00"
                        dur="16s"
                        begin="-3s"
                        repeatCount="indefinite"
                    />
                </stop>
                <stop offset="72%" stopColor="#8B00FF">
                    <animate
                        attributeName="stop-color"
                        values="#8B00FF;#FF0000;#FF7F00;#8B00FF"
                        dur="16s"
                        begin="-6s"
                        repeatCount="indefinite"
                    />
                </stop>
                <stop offset="100%" stopColor="#FFFF00">
                    <animate
                        attributeName="stop-color"
                        values="#FFFF00;#00FF00;#0000FF;#FFFF00"
                        dur="16s"
                        begin="-1.5s"
                        repeatCount="indefinite"
                    />
                </stop>
            </linearGradient>
        </defs>
        <g fill="url(#ggDataCleanLogoGradient)">
            <g>
                <path d="M150.709,172.563c-5.833,0-11.665-2.227-16.115-6.674c-8.901-8.9-8.901-23.332,0-32.231l89.18-89.184c8.902-8.9,23.33-8.902,32.231,0c8.9,8.899,8.9,23.33,0,32.23l-89.181,89.185C162.375,170.337,156.544,172.563,150.709,172.563z"></path>
            </g>
            <g>
                <path d="M150.846,298.976c-18.939,0-37.918-3.449-55.643-10.52c-59.397-23.688-81.321-75.575-86.626-90.913c-13.582-39.274-6.68-81.34,3.358-105.431C34.368,38.27,85.823,4.229,149.576,1.052c12.563-0.617,23.271,9.058,23.896,21.628c0.625,12.57-9.059,23.269-21.63,23.896c-45.757,2.279-82.33,25.856-97.833,63.065c-7.266,17.434-11.101,47.717-2.357,73.003c3.706,10.713,19.016,46.952,60.437,63.474c33.125,13.213,74.083,7.93,101.912-13.139c25.052-18.968,39.418-49.295,39.418-83.207c0-12.587,10.205-22.791,22.791-22.791c12.585,0,22.79,10.203,22.79,22.791c0,48.314-20.951,91.887-57.485,119.546C215.778,288.805,183.371,298.976,150.846,298.976z"></path>
            </g>
        </g>
    </svg>
);

export default GregiusLogo;