/* Fluent Post Newsletter — Meta Box JS */
function fpnInit() {
    var nonce  = document.getElementById('fpn_nonce').value;
    var postId = document.getElementById('fpn_post_id').value;
    var btn    = document.getElementById('fpn-create-btn');

    if (!btn) return; // 미발행 포스트: 버튼 없음

    btn.addEventListener('click', function() {
        btn.disabled    = true;
        btn.textContent = '처리 중...';

        fpnRequest('fpn_create_campaign', { nonce: nonce, post_id: postId }, function(res) {
            fpnShowNotice(res.data.message, 'success');
            fpnUpdateStatus(res.data.status, res.data.campaign_url);
            btn.textContent = '캠페인 내용 업데이트';
            btn.disabled    = false;
        }, function(res) {
            var msg = (res && res.data && res.data.message) ? res.data.message : '오류가 발생했습니다.';
            // 이미 발송된 캠페인인 경우 campaign_url 이 있으면 링크 업데이트
            if (res && res.data && res.data.campaign_url) {
                fpnUpdateStatus(res.data.status, res.data.campaign_url);
            }
            fpnShowNotice(msg, 'error');
            btn.disabled    = false;
            btn.textContent = '캠페인 내용 업데이트';
        });
    });
}

function fpnRequest(action, params, onSuccess, onError) {
    var data = Object.assign({ action: action }, params);
    var body = Object.keys(data).map(function(key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
    }).join('&');

    fetch(window.ajaxurl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    body,
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) { onSuccess(res); } else { onError(res); }
    })
    .catch(function() { onError(null); });
}

function fpnShowNotice(message, type) {
    var el = document.getElementById('fpn-notice');
    el.style.display    = 'block';
    el.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
    el.style.color      = type === 'success' ? '#065f46' : '#991b1b';
    el.textContent      = message;
}

function fpnUpdateStatus(status, campaignUrl) {
    var statusLabels = {
        'draft':             { label: '초안 (FluentCRM에서 발송 설정 필요)', color: '#6b7280' },
        'processing':        { label: '발송 중',   color: '#f59e0b' },
        'pending-scheduled': { label: '예약됨',    color: '#3b82f6' },
        'scheduled':         { label: '예약됨',    color: '#3b82f6' },
        'sent':              { label: '발송 완료', color: '#10b981' },
        'archived':          { label: '보관됨',    color: '#9ca3af' },
        'working':           { label: '처리 중',   color: '#f59e0b' },
    };
    var info = statusLabels[status] || { label: status, color: '#6b7280' };
    var row  = document.getElementById('fpn-status-row');

    row.style.display = 'block';

    var statusText = document.getElementById('fpn-status-text');
    if (statusText) {
        statusText.innerHTML = '<strong style="color:' + info.color + ';">' + info.label + '</strong>';
    }

    var linkEl = document.getElementById('fpn-campaign-link');
    if (linkEl && campaignUrl) {
        linkEl.innerHTML = '<a href="' + campaignUrl + '" target="_blank" style="color:#1d4ed8;">'
            + 'FluentCRM에서 편집 →</a>';
    }
}
