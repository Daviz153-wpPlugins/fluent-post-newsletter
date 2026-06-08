<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class CampaignManager {

    private const META_CAMPAIGN_ID  = '_fpn_campaign_id';
    private const MODIFIABLE_STATUSES = ['draft'];

    public static function createOrUpdate(int $postId): array {
        $existingId = (int) get_post_meta($postId, self::META_CAMPAIGN_ID, true);

        if ($existingId && self::campaignExists($existingId)) {
            $status = self::getStatus($existingId);

            if (!in_array($status, self::MODIFIABLE_STATUSES, true)) {
                return [
                    'success'      => false,
                    'message'      => '이미 발송됐거나 처리 중인 캠페인은 수정할 수 없습니다. (현재 상태: ' . $status . ')',
                    'campaign_id'  => $existingId,
                    'campaign_url' => self::getCampaignUrl($existingId),
                ];
            }

            self::updateCampaign($existingId, $postId);
            $campaignId = $existingId;
            $message    = '캠페인 내용이 업데이트되었습니다.';
        } else {
            $campaignId = self::createCampaign($postId);
            update_post_meta($postId, self::META_CAMPAIGN_ID, $campaignId);
            $message    = 'FluentCRM에 캠페인이 생성되었습니다. 수신자·발송 설정은 FluentCRM에서 하세요.';
        }

        return [
            'success'      => true,
            'message'      => $message,
            'campaign_id'  => $campaignId,
            'campaign_url' => self::getCampaignUrl($campaignId),
            'status'       => self::getStatus($campaignId),
        ];
    }

    private static function createCampaign(int $postId): int {
        $title    = get_the_title($postId);
        $campaign = \FluentCrm\App\Models\Campaign::create([
            'title'           => sanitize_text_field($title),
            'status'          => 'draft',
            'email_subject'   => sanitize_text_field($title),
            'email_body'      => EmailTemplate::render($postId),
            'design_template' => 'raw_html',
        ]);

        return (int) $campaign->id;
    }

    private static function updateCampaign(int $campaignId, int $postId): void {
        $title    = get_the_title($postId);
        $campaign = \FluentCrm\App\Models\Campaign::find($campaignId);
        if (!$campaign) {
            error_log("FPN: campaign {$campaignId} not found in updateCampaign");
            return;
        }

        $campaign->fill([
            'title'         => sanitize_text_field($title),
            'email_subject' => sanitize_text_field($title),
            'email_body'    => EmailTemplate::render($postId),
        ])->save();
    }

    public static function getStatus(int $campaignId): string {
        $campaign = \FluentCrm\App\Models\Campaign::find($campaignId);
        return $campaign ? (string) $campaign->status : '';
    }

    public static function getCampaignUrl(int $campaignId): string {
        return admin_url('admin.php?page=fluentcrm-admin#/email/campaigns/' . $campaignId);
    }

    private static function campaignExists(int $campaignId): bool {
        return \FluentCrm\App\Models\Campaign::where('id', $campaignId)->exists();
    }
}
