<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class MetaBox {

    public function register(): void {
        add_action('add_meta_boxes',              [$this, 'addMetaBox']);
        add_action('admin_enqueue_scripts',       [$this, 'enqueueAssets']);
        add_action('wp_ajax_fpn_create_campaign', [$this, 'ajaxCreateCampaign']);
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
        $isPublished = $post->post_status === 'publish';

        $statusLabels = [
            'draft'             => ['label' => '초안 (FluentCRM에서 발송 설정 필요)', 'color' => '#6b7280'],
            'processing'        => ['label' => '발송 중',    'color' => '#f59e0b'],
            'pending-scheduled' => ['label' => '예약됨',     'color' => '#3b82f6'],
            'scheduled'         => ['label' => '예약됨',     'color' => '#3b82f6'],
            'sent'              => ['label' => '발송 완료',  'color' => '#10b981'],
            'archived'          => ['label' => '보관됨',     'color' => '#9ca3af'],
            'working'           => ['label' => '처리 중',    'color' => '#f59e0b'],
        ];
        $statusInfo  = $statusLabels[$status] ?? null;
        $hasExisting = $campaignId && $statusInfo;
        ?>
        <div id="fpn-meta-box" style="font-size:13px;">
            <input type="hidden" id="fpn_nonce"   value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" id="fpn_post_id" value="<?php echo esc_attr($post->ID); ?>">

            <?php if (!$isPublished): ?>
            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.5;">
                포스트를 <strong>발행</strong>하면 FluentCRM 뉴스레터 캠페인으로 만들 수 있습니다.
            </p>
            <?php else: ?>

            <!-- 현재 캠페인 상태 -->
            <?php if ($hasExisting): ?>
            <div id="fpn-status-row" style="margin-bottom:10px;padding:8px;background:#f9fafb;border-radius:4px;">
                <p style="margin:0 0 4px 0;font-size:12px;color:#6b7280;">현재 캠페인 상태</p>
                <p style="margin:0;font-size:13px;">
                    <strong style="color:<?php echo esc_attr($statusInfo['color']); ?>;">
                        <?php echo esc_html($statusInfo['label']); ?>
                    </strong>
                </p>
                <p style="margin:4px 0 0 0;font-size:12px;">
                    <a href="<?php echo esc_url($campaignUrl); ?>" target="_blank"
                       style="color:#1d4ed8;">FluentCRM에서 편집 →</a>
                </p>
            </div>
            <?php else: ?>
            <div id="fpn-status-row" style="display:none;margin-bottom:10px;padding:8px;background:#f9fafb;border-radius:4px;">
                <p style="margin:0 0 4px 0;font-size:12px;color:#6b7280;">현재 캠페인 상태</p>
                <p style="margin:0;font-size:13px;" id="fpn-status-text"></p>
                <p style="margin:4px 0 0 0;font-size:12px;" id="fpn-campaign-link"></p>
            </div>
            <?php endif; ?>

            <!-- 생성/업데이트 버튼 -->
            <div style="margin-bottom:10px;">
                <button type="button" id="fpn-create-btn"
                        style="width:100%;padding:8px;background:#1d4ed8;color:#fff;border:none;
                               border-radius:4px;cursor:pointer;font-size:13px;font-weight:600;">
                    <?php echo $hasExisting ? '캠페인 내용 업데이트' : 'FluentCRM 캠페인 만들기'; ?>
                </button>
            </div>

            <!-- 알림 메시지 -->
            <div id="fpn-notice" style="display:none;padding:8px;border-radius:4px;font-size:12px;"></div>

            <?php endif; ?>
        </div>

        <script>
        (function() {
            var $ = window.jQuery;
            $(document).ready(function() { fpnInit(); });
        })();
        </script>
        <?php
    }

    public function ajaxCreateCampaign(): void {
        check_ajax_referer('fpn_nonce', 'nonce');

        $postId = (int) sanitize_text_field($_POST['post_id'] ?? '');
        $post   = get_post($postId);

        if (!$postId || !$post || $post->post_status !== 'publish') {
            wp_send_json_error(['message' => '발행된 포스트만 뉴스레터로 만들 수 있습니다.']);
        }

        // 오브젝트 레벨 권한: 해당 포스트를 편집할 수 있고 publish_posts 이상이어야 함
        if (!current_user_can('edit_post', $postId) || !current_user_can('publish_posts')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }

        $result = CampaignManager::createOrUpdate($postId);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
