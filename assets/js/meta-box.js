/* Fluent Post Newsletter — Meta Box JS */
function fpnInit() {
    var nonce  = document.getElementById('fpn_nonce').value;
    var postId = document.getElementById('fpn_post_id').value;

    // 예약 발송 선택 시 일시 필드 토글
    document.querySelectorAll('input[name="fpn_send_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var scheduleRow = document.getElementById('fpn-schedule-row');
            scheduleRow.style.display = this.value === 'scheduled' ? 'block' : 'none';
        });
    });

    // 태그 목록 로드
    fpnRequest('fpn_get_tags', { nonce: nonce }, function(res) {
        var container = document.getElementById('fpn-tags-list');
        if (!res.data.tags || res.data.tags.length === 0) {
            container.innerHTML = '<span style="color:#9ca3af;">FluentCRM에 등록된 태그가 없습니다.</span>';
            return;
        }
        container.innerHTML = res.data.tags.map(function(tag) {
            return '<label style="display:inline-block;margin-right:8px;margin-bottom:4px;">'
                 + '<input type="checkbox" name="fpn_tag_ids[]" value="' + tag.id + '"> '
                 + tag.title + ' <span style="color:#9ca3af;">(' + tag.count + '명)</span>'
                 + '</label>';
        }).join('');
    }, function() {
        document.getElementById('fpn-tags-list').innerHTML =
            '<span style="color:#ef4444;">태그 로드 실패</span>';
    });

    // 뉴스레터 생성 버튼
    document.getElementById('fpn-create-btn').addEventListener('click', function() {
        var btn = this;

        var tagIds = Array.from(
            document.querySelectorAll('input[name="fpn_tag_ids[]"]:checked')
        ).map(function(el) { return el.value; });

        var contentType = document.querySelector('input[name="fpn_content_type"]:checked');
        var sendType    = document.querySelector('input[name="fpn_send_type"]:checked');
        var scheduledAt = document.getElementById('fpn_scheduled_at');

        if (tagIds.length === 0) {
            fpnShowNotice('수신자 태그를 하나 이상 선택해주세요.', 'error');
            return;
        }

        if (sendType.value === 'scheduled' && !scheduledAt.value) {
            fpnShowNotice('예약 일시를 입력해주세요.', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = '처리 중...';

        var params = {
            nonce:        nonce,
            post_id:      postId,
            content_type: contentType ? contentType.value : 'full',
            send_type:    sendType ? sendType.value : 'now',
            scheduled_at: scheduledAt ? scheduledAt.value : '',
        };
        tagIds.forEach(function(id) {
            params['tag_ids[]'] = params['tag_ids[]']
                ? [].concat(params['tag_ids[]'], id)
                : id;
        });

        fpnRequest('fpn_create_campaign', params, function(res) {
            fpnShowNotice(res.data.message, 'success');
            fpnUpdateStatus(res.data.campaign_id, res.data.status, res.data.campaign_url);
            btn.disabled = false;
            btn.textContent = '뉴스레터 업데이트';
        }, function(res) {
            var msg = (res && res.data && res.data.message) ? res.data.message : '오류가 발생했습니다.';
            fpnShowNotice(msg, 'error');
            btn.disabled = false;
            btn.textContent = '뉴스레터 생성';
        });
    });
}

function fpnRequest(action, params, onSuccess, onError) {
    var data = Object.assign({ action: action }, params);
    var body = Object.keys(data).map(function(key) {
        var val = data[key];
        if (Array.isArray(val)) {
            return val.map(function(v) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(v);
            }).join('&');
        }
        return encodeURIComponent(key) + '=' + encodeURIComponent(val);
    }).join('&');

    fetch(window.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
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

function fpnUpdateStatus(campaignId, status, campaignUrl) {
    var statusLabels = {
        'draft':             { label: '초안',      color: '#6b7280' },
        'processing':        { label: '발송 중',   color: '#f59e0b' },
        'pending-scheduled': { label: '예약됨',    color: '#3b82f6' },
        'scheduled':         { label: '예약됨',    color: '#3b82f6' },
        'sent':              { label: '발송 완료', color: '#10b981' },
    };
    var info = statusLabels[status] || { label: status, color: '#6b7280' };
    var row  = document.getElementById('fpn-status-row');

    row.style.display = 'block';
    row.innerHTML = '<p style="margin:0;font-size:12px;color:#6b7280;">'
        + '상태: <strong style="color:' + info.color + ';">' + info.label + '</strong>'
        + '&nbsp;<a href="' + campaignUrl + '" target="_blank" style="color:#1d4ed8;">'
        + 'FluentCRM에서 보기 →</a></p>';
}
