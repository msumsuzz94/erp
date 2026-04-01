<?php
/**
 * SMS Send Center
 * Connect phone via QR code scan (like Google Messages for Web)
 * Uses WebSocket bridge for real-time phone-to-browser SMS sending
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Build recipient lists (wrapped in try-catch for safety)
try {
    $customers = db_query("SELECT id, name, phone FROM customers WHERE phone IS NOT NULL AND phone != '' ORDER BY name ASC") ?: [];
} catch (Exception $e) { $customers = []; }

try {
    $leads = db_query("SELECT id, organization_name, contact_person, mobile FROM leads WHERE mobile IS NOT NULL AND mobile != '' ORDER BY created_at DESC") ?: [];
} catch (Exception $e) { $leads = []; }

// Fetch templates
try {
    $templates = db_query("SELECT id, name, body FROM marketing_templates WHERE channel = 'sms' AND status = 'active' ORDER BY name ASC") ?: [];
} catch (Exception $e) { $templates = []; }

// Generate unique session for QR pairing
$session_id = bin2hex(random_bytes(16));
$qr_data = json_encode([
    'type' => 'erp_sms_bridge',
    'session' => $session_id,
    'server' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
    'erp_url' => BASE_URL
]);

$page_title = 'SMS Send Center';
include __DIR__ . '/../../templates/header.php';
?>

<style>
/* QR Pairing Screen */
.qr-pairing { text-align: center; padding: 40px 20px; }
.qr-pairing .qr-frame { 
    display: inline-block; padding: 20px; border: 3px solid #4e73df; border-radius: 16px;
    background: #fff; box-shadow: 0 8px 32px rgba(78,115,223,0.15);
}
.qr-pairing .qr-frame img { width: 200px; height: 200px; }
.phone-connected { display: none; }
.pairing-steps { text-align: left; max-width: 400px; margin: 20px auto; }
.pairing-steps .step { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.pairing-steps .step-num { 
    width: 28px; height: 28px; border-radius: 50%; background: #4e73df; color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 13px; flex-shrink: 0;
}

/* Connection Status Bar */
.conn-bar { 
    padding: 10px 20px; border-radius: 10px; display: flex; align-items: center; gap: 10px;
    margin-bottom: 20px; font-weight: 500;
}
.conn-bar.connected { background: rgba(28,200,138,0.1); border: 1px solid rgba(28,200,138,0.3); color: #1cc88a; }
.conn-bar.disconnected { background: rgba(231,74,59,0.1); border: 1px solid rgba(231,74,59,0.3); color: #e74a3b; }
.conn-bar.waiting { background: rgba(246,194,62,0.1); border: 1px solid rgba(246,194,62,0.3); color: #f6c23e; }
.pulse-dot { width: 10px; height: 10px; border-radius: 50%; animation: pulse 1.5s infinite; }
.pulse-dot.green { background: #1cc88a; }
.pulse-dot.red { background: #e74a3b; }
.pulse-dot.yellow { background: #f6c23e; }
@keyframes pulse { 0%,100%{ opacity:1; transform:scale(1); } 50%{ opacity:0.5; transform:scale(0.8); } }

/* Recipient Tags */
.recipient-tag { 
    display: inline-flex; align-items: center; gap: 5px; background: var(--bg-secondary); 
    border: 1px solid var(--border-color); padding: 4px 12px; border-radius: 20px; 
    margin: 3px; font-size: 13px;
}
.recipient-tag .remove { cursor: pointer; color: #e74a3b; font-weight: bold; margin-left: 4px; }

/* Send Log */
.send-log { max-height: 350px; overflow-y: auto; }
.log-entry { padding: 8px 12px; border-bottom: 1px solid var(--border-color); font-size: 13px; display: flex; align-items: center; gap: 8px; }
.log-entry.success i { color: #1cc88a; }
.log-entry.failed i { color: #e74a3b; }
</style>

<div class="row">
    <!-- Left: QR Pairing + Compose -->
    <div class="col-lg-7">
        
        <!-- Connection Status Bar -->
        <div class="conn-bar disconnected" id="connBar">
            <div class="pulse-dot red" id="connDot"></div>
            <span id="connText">Phone Not Connected</span>
            <div class="ms-auto">
                <button class="btn btn-sm btn-outline-primary" id="showQRBtn"><i class="fas fa-qrcode"></i> Connect Phone</button>
            </div>
        </div>

        <!-- QR Pairing Panel -->
        <div class="card shadow-sm mb-4" id="qrCard">
            <div class="card-body qr-pairing">
                <h4 class="mb-3"><i class="fas fa-mobile-alt text-primary"></i> Connect Your Phone</h4>
                <p class="text-muted">Scan the QR code from your phone to send SMS using your SIM card</p>
                
                <div class="qr-frame mb-3" id="qrFrame">
                    <?php
                    $bridge_url = BASE_URL . '/modules/marketing/sms-bridge.php?session=' . $session_id;
                    $qr_img_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&format=png&data=' . urlencode($bridge_url);
                    ?>
                    <img src="<?= $qr_img_url ?>" alt="QR Code" width="200" height="200" id="qrImage" style="image-rendering: pixelated;">
                </div>
                
                <div class="pairing-steps">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div>Open <strong>any QR scanner</strong> on your Android phone</div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div>Scan the QR code above - it will open a <strong>bridge page</strong> on your phone</div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div>Tap <strong>"Allow"</strong> on your phone to enable SMS sending</div>
                    </div>
                    <div class="step">
                        <div class="step-num">4</div>
                        <div>Once connected, compose and send SMS from this screen</div>
                    </div>
                </div>
                
                <div class="alert alert-warning small mt-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> Your phone and this computer must be on the <strong>same network</strong>. 
                    SMS charges apply from your carrier. This feature uses the <code>sms:</code> intent on your phone.
                </div>
                
                <hr>
                <p class="text-muted small mb-2">Or use these alternative methods:</p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm" onclick="useNativeSMS()">
                        <i class="fas fa-external-link-alt"></i> Open Phone SMS App
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.open('https://messages.google.com/web/', '_blank')">
                        <i class="fab fa-google"></i> Google Messages Web
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="useBulkAPI()">
                        <i class="fas fa-server"></i> Use SMS Gateway API
                    </button>
                </div>
            </div>
        </div>

        <!-- Compose Panel (shown after connection or fallback) -->
        <div class="card shadow-sm mb-4" id="composeCard">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-pen"></i> Compose Message</h6>
            </div>
            <div class="card-body">
                <!-- Recipients -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Recipients</label>
                    <div class="input-group mb-2">
                        <select id="recipientSource" class="form-select">
                            <option value="">-- Add from database --</option>
                            <optgroup label="Customers">
                                <?php foreach($customers as $c): ?>
                                    <option value="<?= htmlspecialchars($c['phone']) ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                                        <?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Leads">
                                <?php foreach($leads as $l): ?>
                                    <option value="<?= htmlspecialchars($l['mobile']) ?>" data-name="<?= htmlspecialchars($l['organization_name'] ?: $l['contact_person']) ?>">
                                        <?= htmlspecialchars($l['organization_name'] ?: $l['contact_person']) ?> (<?= $l['mobile'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <button class="btn btn-success" id="addRecipientBtn"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="input-group mb-2">
                        <input type="tel" id="manualPhone" class="form-control" placeholder="Or type phone number manually">
                        <button class="btn btn-outline-success" id="addManualBtn"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <div id="recipientsList" class="p-2 border rounded bg-light" style="min-height: 36px;">
                        <small class="text-muted">No recipients added</small>
                    </div>
                    <small class="text-muted">Total: <strong id="recipientCount">0</strong></small>
                </div>

                <!-- Template -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Template</label>
                    <select id="templateSelect" class="form-select">
                        <option value="">-- Use a template --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= htmlspecialchars($t['body']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Message -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Message</label>
                    <textarea id="message" class="form-control" rows="4" placeholder="Type your message..."></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <small id="charCount" class="text-muted">0 chars</small>
                        <small class="text-muted">English: 160/SMS | Unicode: 70/SMS</small>
                    </div>
                </div>

                <!-- Send Buttons -->
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" id="sendBtn">
                        <i class="fas fa-paper-plane"></i> Send via Connect
                    </button>
                    <button class="btn btn-outline-primary" id="openSmsBtn" title="Open Phone SMS">
                        <i class="fas fa-mobile-alt"></i>
                    </button>
                    <button class="btn btn-outline-success" onclick="openGoogleMessagesWithText()" title="Open Google Messages">
                        <i class="fab fa-google"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Log & Stats -->
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 d-flex justify-content-between">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-chart-pie"></i> Session Stats</h6>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-4">
                        <div class="text-muted small">Total</div>
                        <div class="h3 fw-bold" id="statTotal">0</div>
                    </div>
                    <div class="col-4">
                        <div class="text-success small">Sent</div>
                        <div class="h3 fw-bold text-success" id="statSent">0</div>
                    </div>
                    <div class="col-4">
                        <div class="text-danger small">Failed</div>
                        <div class="h3 fw-bold text-danger" id="statFailed">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-history"></i> Send Log</h6>
            </div>
            <div class="card-body send-log p-0" id="sendLog">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    No messages sent yet
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
const SESSION_ID = '<?= $session_id ?>';
const QR_DATA = '<?= BASE_URL ?>/modules/marketing/sms-bridge.php?session=' + SESSION_ID;
let recipients = [];
let stats = { total: 0, sent: 0, failed: 0 };
let phoneConnected = false;

// Check if QR image loaded, show fallback link if not
var qrImg = document.getElementById('qrImage');
if (qrImg) {
    qrImg.onerror = function() {
        this.parentNode.innerHTML = '<div class="p-3 text-center"><p class="text-warning mb-2"><i class="fas fa-exclamation-triangle"></i> QR Code could not load</p><a href="' + QR_DATA + '" class="btn btn-primary btn-sm" target="_blank"><i class="fas fa-external-link-alt"></i> Open Bridge Link on Phone</a></div>';
    };
}

$(document).ready(function() {
    // Template select
    $('#templateSelect').change(function() {
        if ($(this).val()) {
            $('#message').val($(this).val());
            updateCharCount();
        }
    });
    
    // Char count
    $('#message').on('input', updateCharCount);
    
    // Add recipient from dropdown
    $('#addRecipientBtn').click(function() {
        let sel = $('#recipientSource');
        if (sel.val()) {
            addRecipient(sel.val(), sel.find(':selected').data('name') || sel.val());
            sel.val('');
        }
    });
    
    // Add manual
    $('#addManualBtn').click(function() {
        let phone = $('#manualPhone').val().trim().replace(/[^0-9+]/g, '');
        if (phone) { addRecipient(phone, phone); $('#manualPhone').val(''); }
    });
    
    // Enter key for manual phone
    $('#manualPhone').keypress(function(e) {
        if (e.which === 13) { e.preventDefault(); $('#addManualBtn').click(); }
    });
    
    // Show/Hide QR
    $('#showQRBtn').click(function() {
        $('#qrCard').slideToggle();
    });
    
    // Send SMS
    $('#sendBtn').click(function() {
        let msg = $('#message').val().trim();
        if (!msg) { alert('Please enter a message'); return; }
        
        let manualPhone = $('#manualPhone').val().trim().replace(/[^0-9+]/g, '');
        if (manualPhone) {
            addRecipient(manualPhone, manualPhone);
            $('#manualPhone').val('');
        }
        
        if (recipients.length === 0) { alert('Please add at least one recipient'); return; }
        
        // Use native SMS intent (works on all devices)
        sendViaNativeIntent(msg);
    });
    
    // Open SMS app button
    $('#openSmsBtn').click(function() {
        let manualPhone = $('#manualPhone').val().trim().replace(/[^0-9+]/g, '');
        if (manualPhone) {
            addRecipient(manualPhone, manualPhone);
            $('#manualPhone').val('');
        }
        
        let msg = $('#message').val().trim();
        if (recipients.length === 0) { alert('Add a recipient first'); return; }
        openNativeSMS(recipients[0].phone, msg);
    });
    
    // Poll for phone connection (check every 3 seconds)
    setInterval(checkPhoneConnection, 3000);
});

function updateCharCount() {
    let text = $('#message').val();
    let len = text.length;
    let isUnicode = /[^\x00-\x7F]/.test(text);
    let perSms = isUnicode ? 70 : 160;
    let count = len === 0 ? 0 : Math.ceil(len / perSms);
    $('#charCount').text(len + ' chars (' + count + ' SMS' + (isUnicode ? ' Unicode' : '') + ')');
}

function addRecipient(phone, name) {
    if (recipients.find(r => r.phone === phone)) return;
    recipients.push({ phone, name });
    renderRecipients();
}

function removeRecipient(idx) {
    recipients.splice(idx, 1);
    renderRecipients();
}

function renderRecipients() {
    if (recipients.length === 0) {
        $('#recipientsList').html('<small class="text-muted">No recipients added</small>');
    } else {
        let html = recipients.map((r, i) => 
            '<span class="recipient-tag">' + r.name + ' <span class="remove" onclick="removeRecipient(' + i + ')">&times;</span></span>'
        ).join('');
        $('#recipientsList').html(html);
    }
    $('#recipientCount').text(recipients.length);
}

function sendViaNativeIntent(msg) {
    let btn = $('#sendBtn');
    
    // If phone is connected via QR bridge, queue it to the API
    if (phoneConnected) {
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Queueing...');
        
        let tasks = recipients.map(r => ({phone: r.phone, message: msg}));
        
        $.ajax({
            url: BASE_URL + '/api/marketing/sms-bridge-send.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                session: SESSION_ID,
                tasks: tasks
            }),
            success: function(resp) {
                if (resp && resp.status) {
                    stats.sent += tasks.length;
                    stats.total += tasks.length;
                    updateStats();
                    addLog('Queued ' + tasks.length + ' messages to connected phone', 'success');
                    showToast('Messages queued to phone successfully', 'success');
                    
                    // Reset form
                    $('#message').val('');
                    updateCharCount();
                    recipients = [];
                    renderRecipients();
                } else {
                    addLog('Failed to queue messages: ' + (resp.error || 'Unknown error'), 'failed');
                    showToast('Failed to send: ' + (resp.error || 'Unknown error'), 'error');
                }
            },
            error: function() {
                addLog('Error communicating with SMS bridge API', 'failed');
                showToast('API communication error', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send via Connect');
            }
        });
        
        return;
    }

    // Phone not connected fallback: use native sms intents sequentially
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    
    // For each recipient, create SMS intent
    let idx = 0;
    function sendNext() {
        if (idx >= recipients.length) {
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send via Connect');
            return;
        }
        
        let r = recipients[idx];
        stats.total++;
        
        // Open native SMS with pre-filled message
        openNativeSMS(r.phone, msg);
        
        stats.sent++;
        addLog('Opened SMS app for ' + r.name + ' (' + r.phone + ')', 'success');
        updateStats();
        
        idx++;
        if (idx < recipients.length) {
            setTimeout(sendNext, 1500); // Delay between each
        } else {
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send via Connect');
        }
    }
    sendNext();
}

function openNativeSMS(phone, msg) {
    let ua = navigator.userAgent.toLowerCase();
    let isApple = (ua.indexOf("iphone") > -1 || ua.indexOf("ipad") > -1);
    let sep = isApple ? "&" : "?";
    
    // Some Android apps prefer %20 over + for spaces in SMS intents
    let encodedMsg = encodeURIComponent(msg).replace(/\+/g, "%20");
    let intentUrl = 'sms:' + encodeURIComponent(phone) + sep + 'body=' + encodedMsg;
    
    // Attempt to open the intent
    try {
        let win = window.open(intentUrl, '_blank');
        if (!win && !isApple) {
            // Fallback for popups blocked or standalone mode where window.open fails
            window.location.href = intentUrl;
        }
    } catch(e) {
        window.location.href = intentUrl;
    }
}

function useNativeSMS() {
    let msg = $('#message').val().trim();
    if (recipients.length > 0) {
        openNativeSMS(recipients[0].phone, msg);
    } else {
        window.open('sms:', '_blank');
    }
}

function openGoogleMessagesWithText() {
    let manualPhone = $('#manualPhone').val().trim().replace(/[^0-9+]/g, '');
    if (manualPhone) {
        addRecipient(manualPhone, manualPhone);
        $('#manualPhone').val('');
    }

    let msg = $('#message').val().trim();
    if (recipients.length === 0) { 
        alert('Please add a recipient first to copy their number.'); 
        window.open('https://messages.google.com/web/', '_blank');
        return; 
    }
    
    // Copy the text to clipboard to make it easier to paste into Google Messages
    let textToCopy = "To: " + recipients[0].phone + "\n\n" + msg;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(msg).then(function() {
            alert('Message copied to clipboard! Paste it in Google Messages for ' + recipients[0].phone);
            window.open('https://messages.google.com/web/', '_blank');
        });
    } else {
        alert('Please paste this in Google Messages for ' + recipients[0].phone + ':\n\n' + msg);
        window.open('https://messages.google.com/web/', '_blank');
    }
}

function useBulkAPI() {
    alert('To use SMS Gateway API, configure your API settings in:\nNotification > Channel Configuration\n\nThen use Campaign Create to send bulk messages via API.');
}

function checkPhoneConnection() {
    // Check if phone bridge is connected via AJAX
    $.get('<?= BASE_URL ?>/api/marketing/sms-bridge-status.php', { session: SESSION_ID }, function(res) {
        if (res && res.connected) {
            setConnected(true, res.phone_model || 'Phone');
        }
    }).fail(function() {
        // API not available yet, that's OK
    });
}

function setConnected(connected, deviceName) {
    if (connected && !phoneConnected) {
        phoneConnected = true;
        $('#connBar').removeClass('disconnected waiting').addClass('connected');
        $('#connDot').removeClass('red yellow').addClass('green');
        $('#connText').html('<i class="fas fa-check-circle"></i> Connected: ' + deviceName);
        $('#qrCard').slideUp();
        addLog('Phone connected: ' + deviceName, 'success');
    }
}

function addLog(text, type) {
    let time = new Date().toLocaleTimeString();
    let icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    let cls = type === 'success' ? 'success' : 'failed';
    
    let existing = $('#sendLog').html();
    if (existing.includes('No messages sent')) {
        $('#sendLog').html('');
    }
    
    $('#sendLog').prepend(
        '<div class="log-entry ' + cls + '">' +
        '<i class="fas ' + icon + '"></i> ' +
        '<span class="flex-grow-1">' + text + '</span>' +
        '<small class="text-muted">' + time + '</small>' +
        '</div>'
    );
}

function updateStats() {
    $('#statTotal').text(stats.total);
    $('#statSent').text(stats.sent);
    $('#statFailed').text(stats.failed);
}
</script>
