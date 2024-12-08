@if($debugEnabled)
    <div id="debug-info" style="position: fixed; top: 0; left: 0; z-index: 9999; background: rgba(0,0,0,0.8); color: white; padding: 10px; width: 100%; font-size: 12px; height: 100%; overflow-y: auto;">
        <div style="margin-bottom: 10px; border-bottom: 1px solid white;">Debug Information</div>
        <div id="debug-content"></div>
        <div id="static-debug">
            <div>Initial Load Time: <script>document.write(new Date().toLocaleTimeString())</script></div>
            <div>JavaScript Enabled: Yes (if you see this)</div>
            <div>URL: <script>document.write(window.location.href)</script></div>
            <noscript>
                <div style="color: red;">JavaScript is DISABLED</div>
            </noscript>
        </div>
        <div id="resource-check">
            <script>
                document.write('<div>Inline JavaScript: Working</div>');

                window.addEventListener('load', function() {
                    var resourceCheck = document.getElementById('resource-check');
                    if(resourceCheck) {
                        resourceCheck.innerHTML += '<div>Window Load Event: Fired</div>';
                        resourceCheck.innerHTML += '<div>jQuery: ' + (typeof jQuery !== 'undefined' ? 'Loaded' : 'Not Loaded') + '</div>';
                        resourceCheck.innerHTML += '<div>Bootstrap: ' + (typeof bootstrap !== 'undefined' ? 'Loaded' : 'Not Loaded') + '</div>';
                    }
                });
            </script>
        </div>
    </div>
    </script>
@endif
