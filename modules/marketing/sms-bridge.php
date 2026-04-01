<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Bridge - ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; }
        .bridge-card { background: #fff; border-radius: 20px; padding: 40px 30px; max-width: 400px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .bridge-card .icon { font-size: 60px; color: #1cc88a; margin-bottom: 15px; }
        .bridge-card .icon.waiting { color: #f6c23e; }
        .bridge-card .icon.error { color: #e74a3b; }
        .phone-info { font-size: 13px; color: #666; }
        .sms-queue { max-height: 300px; overflow-y: auto; text-align: left; margin-top: 15px; }
        .sms-item { padding: 10px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 6px; font-size: 13px; }
        .sms-item .phone { font-weight: bold; color: #4e73df; }
    </style>
</head>
<body>
    <div class="bridge-card" id="bridgeCard">
        <div class="icon waiting"><i class="fas fa-link"></i></div>
        <h4>SMS Bridge</h4>
        <p class="text-muted">Connecting to ERP system...</p>
        
        <div id="statusArea">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Establishing connection...</p>
        </div>
        
        <div id="connectedArea" style="display:none;">
            <div class="icon" style="font-size:40px;"><i class="fas fa-check-circle"></i></div>
            <h5 class="text-success">Connected!</h5>
            <p class="text-muted">Your phone is now linked to the ERP SMS Center.</p>
            <p class="phone-info">When the admin sends an SMS from the ERP, it will open your SMS app with the message pre-filled.</p>
            <div class="alert alert-warning small mt-3">
                <i class="fas fa-exclamation-triangle"></i> Keep this page open to maintain the connection.
            </div>
            
            <div class="sms-queue" id="smsQueue">
                <!-- Incoming SMS tasks will appear here -->
            </div>
        </div>
    </div>

    <script>
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session');
    
    if (!sessionId) {
        document.getElementById('statusArea').innerHTML = '<div class="icon error"><i class="fas fa-times-circle"></i></div><p class="text-danger">Invalid QR code. Please scan again.</p>';
    } else {
        // Register this phone with the session
        registerPhone();
        
        // Start polling for SMS tasks
        setInterval(pollForSMSTasks, 3000);
    }
    
    function registerPhone() {
        const deviceInfo = navigator.userAgent;
        
        fetch(getBaseUrl() + '/api/marketing/sms-bridge-register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session: sessionId,
                device: deviceInfo,
                phone_model: getPhoneModel(deviceInfo)
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status) {
                document.getElementById('statusArea').style.display = 'none';
                document.getElementById('connectedArea').style.display = 'block';
            }
        })
        .catch(() => {
            // Fallback: just show connected state
            setTimeout(() => {
                document.getElementById('statusArea').style.display = 'none';
                document.getElementById('connectedArea').style.display = 'block';
            }, 2000);
        });
    }
    
    function pollForSMSTasks() {
        fetch(getBaseUrl() + '/api/marketing/sms-bridge-poll.php?session=' + sessionId)
        .then(r => r.json())
        .then(data => {
            if (data.tasks && data.tasks.length > 0) {
                data.tasks.forEach(task => {
                    // Generate intent URL
                    const ua = navigator.userAgent.toLowerCase();
                    const sep = (ua.indexOf("iphone") > -1) ? "&" : "?";
                    const intentUrl = 'sms:' + task.phone + sep + 'body=' + encodeURIComponent(task.message);
                    
                    // Attempt to auto-open
                    try { window.location.href = intentUrl; } catch(e) {}
                    
                    // Show in queue with a clickable fallback
                    addToQueue(task.phone, task.message, intentUrl);
                });
            }
        })
        .catch(() => { /* Polling failed, retry next cycle */ });
    }
    
    function addToQueue(phone, message, intentUrl) {
        const queue = document.getElementById('smsQueue');
        const item = document.createElement('div');
        item.className = 'sms-item';
        
        // Add a "Send Now" button in case auto-open got blocked
        let btnHtml = '';
        if (intentUrl) {
           btnHtml = '<div class="mt-2"><a href="' + intentUrl + '" class="btn btn-sm btn-primary w-100"><i class="fas fa-paper-plane"></i> Send Now (Tap if not opened)</a></div>';
        }
        
        item.innerHTML = '<span class="phone">' + phone + '</span><br>' + message.substring(0, 50) + '...' + btnHtml;
        queue.prepend(item);
    }
    
    function getBaseUrl() {
        // Extract base URL from referrer or current location
        const path = window.location.pathname;
        const idx = path.indexOf('/modules/');
        return window.location.origin + path.substring(0, idx);
    }
    
    function getPhoneModel(ua) {
        if (/samsung/i.test(ua)) return 'Samsung';
        if (/xiaomi|redmi|poco/i.test(ua)) return 'Xiaomi';
        if (/oppo/i.test(ua)) return 'Oppo';
        if (/vivo/i.test(ua)) return 'Vivo';
        if (/realme/i.test(ua)) return 'Realme';
        if (/huawei/i.test(ua)) return 'Huawei';
        if (/iphone/i.test(ua)) return 'iPhone';
        if (/pixel/i.test(ua)) return 'Pixel';
        return 'Android Phone';
    }
    </script>
</body>
</html>
