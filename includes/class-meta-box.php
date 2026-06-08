<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class MetaBox {

    public function register(): void {
        add_action('add_meta_boxes',              [$this, 'addMetaBox']);
        add_action('admin_enqueue_scripts',       [$this, 'enqueueAssets']);
        add_action('wp_ajax_fpn_create_campaign', [$this, 'ajaxCreateCampaign']);
        add_action('wp_ajax_fpn_get_tags',        [$this, 'ajaxGetTags']);
    }

    public function enqueueAssets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

        wp_enqueue_style(
            'fpn-meta-box',
            FPN_URL . 'assets/css/meta-box.css',
            [],
            FPN_VERSION
        );

        wp_enqueue_script(
            'fpn-meta-box',
            FPN_URL . 'assets/js/meta-box.js',
            [],
            FPN_VERSION,
            true
        );
    }

    public function addMetaBox(): void {
        add_meta_box(
            'fpn_newsletter',
            '뉴스레터 발송',
            [$this, 'render'],
            'post',
            'side',
            'high'
        );
    }

    public function render(\WP_Post $post): void {
        $campaignId  = (int) get_post_meta($post->ID, '_fpn_campaign_id', true);
        $campaignUrl = $campaignId ? CampaignManager::getCampaignUrl($campaignId) : '';
        $status      = $campaignId ? CampaignManager::getStatus($campaignId) : '';
        $nonce       = wp_create_nonce('fpn_nonce');

        $statusLabels = [
            'draft'             => ['label' => '초안', 'color' => '#6b7280'],
            'processing'        => ['label' => '발송 중', 'color' => '#f59e0b'],
            'pending-scheduled' => ['label' => '예약됨', 'color' => '#3b82f6'],
            'scheduled'         => ['label' => '예약됨', 'color' => '#3b82f6'],
            'sent'              => ['label' => '발송 완료', 'color' => '#10b981'],
            'archived'          => ['label' => '보관됨', 'color' => '#9ca3af'],
        ];
        $statusInfo = $statusLabels[$status] ?? null;
        ?>
        <div id="fpn-meta-box" style="font-size:13px;">
            <input type="hidden" id="fpn_nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" id="fpn_post_id" value="<?php echo esc_attr($post->ID); ?>">

            <!-- 수신자 태그 -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">수신자 태그</label>
                <div id="fpn-tags-list" style="line-height:2;">
                    <span style="color:#9ca3af;">태그 불러오는 중...</span>
                </div>
            </div>

            <!-- 콘텐츠 방식 -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">콘텐츠</label>
                <label style="margin-right:12px;">
                    <input type="radio" name="fpn_content_type" value="full" checked> 전체 본문
                </label>
                <label>
                    <input type="radio" name="fpn_content_type" value="excerpt"> 요약 발췌
                </label>
            </div>

            <!-- 발송 방식 -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">발송</label>
                <label style="display:block;margin-bottom:4px;">
                    <input type="radio" name="fpn_send_type" value="now" checked> 즉시 발송
                </label>
                <label style="display:block;margin-bottom:4px;">
                    <input type="radio" name="fpn_send_type" value="scheduled"> 예약 발송
                </label>
                <label style="display:block;">
                    <input type="radio" name="fpn_send_type" value="draft"> 임시 저장 (FluentCRM에서 직접 발송)
                </label>
            </div>

            <!-- 예약 일시 -->
            <div id="fpn-schedule-row" style="margin-bottom:12px;display:none;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">예약 일시</label>
                <input type="datetime-local" id="fpn_scheduled_at"
                       style="width:100%;box-sizing:border-box;padding:4px 6px;">
            </div>

            <!-- 생성 버튼 -->
            <div style="margin-bottom:12px;">
                <button type="button" id="fpn-create-btn"
                        style="width:100%;padding:8px;background:#1d4ed8;color:#fff;border:none;
                               border-radius:4px;cursor:pointer;font-size:13px;font-weight:600;">
                    뉴스레터 생성
                </button>
            </div>

            <!-- 상태 표시 -->
            <div id="fpn-status-row" style="<?php echo $campaignId ? '' : 'display:none;'; ?>">
                <?php if ($campaignId && $statusInfo): ?>
                <p style="margin:0;font-size:12px;color:#6b7280;">
                    상태:
                    <strong style="color:<?php echo esc_attr($statusInfo['color']); ?>;">
                        <?php echo esc_html($statusInfo['label']); ?>
                    </strong>
                    &nbsp;
                    <a href="<?php echo esc_url($campaignUrl); ?>" target="_blank"
                       style="color:#1d4ed8;">FluentCRM에서 보기 →</a>
                </p>
                <?php endif; ?>
            </div>

            <!-- 알림 메시지 -->
            <div id="fpn-notice" style="display:none;margin-top:8px;padding:8px;
                 border-radius:4px;font-size:12px;"></div>
        </div>

        <script>
        (function() {
            var $ = window.jQuery;
            $(document).ready(function() {
                fpnInit();
            });
        })();
        </script>
        <?php
    }

    public function ajaxCreateCampaign(): void {
        check_ajax_referer('fpn_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        $postId      = (int) sanitize_text_field($_POST['post_id'] ?? '');
        $tagIds      = array_map('intval', (array) ($_POST['tag_ids'] ?? []));
        $contentType = in_array($_POST['content_type'] ?? '', ['full', 'excerpt'], true)
                       ? $_POST['content_type'] : 'full';
        $sendType    = in_array($_POST['send_type'] ?? '', ['now', 'scheduled', 'draft'], true)
                       ? $_POST['send_type'] : 'now';
        $scheduledAt = sanitize_text_field($_POST['scheduled_at'] ?? '');

        if (!$postId || !get_post($postId)) {
            wp_send_json_error(['message' => '유효하지 않은 포스트입니다.']);
        }

        if (empty($tagIds)) {
            wp_send_json_error(['message' => '수신자 태그를 하나 이상 선택해주세요.']);
        }

        $result = CampaignManager::createOrUpdate($postId, [
            'tag_ids'      => $tagIds,
            'content_type' => $contentType,
            'send_type'    => $sendType,
            'scheduled_at' => $scheduledAt,
        ]);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajaxGetTags(): void {
        check_ajax_referer('fpn_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        $tags = [];
        if (function_exists('FluentCrmApi')) {
            foreach (FluentCrmApi('tags')->get() as $tag) {
                $tags[] = [
                    'id'    => (int) $tag->id,
                    'title' => esc_html($tag->title),
                    'count' => (int) $tag->subscribersCount,
                ];
            }
        }

        wp_send_json_success(['tags' => $tags]);
    }
}
