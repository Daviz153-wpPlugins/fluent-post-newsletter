<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class CampaignManager {

    private const META_CAMPAIGN_ID = '_fpn_campaign_id';
    private const META_STATUS      = '_fpn_campaign_status';

    // 수정이 허용된 캠페인 상태값
    private const MODIFIABLE_STATUSES = ['draft'];

    public static function createOrUpdate(int $postId, array $options): array {
        $existingId = (int) get_post_meta($postId, self::META_CAMPAIGN_ID, true);

        if ($existingId && self::campaignExists($existingId)) {
            $status = self::getStatus($existingId);

            if (!in_array($status, self::MODIFIABLE_STATUSES, true)) {
                return [
                    'success'      => false,
                    'message'      => '이미 발송된 캠페인은 수정할 수 없습니다. (현재 상태: ' . $status . ')',
                    'campaign_id'  => $existingId,
                    'campaign_url' => self::getCampaignUrl($existingId),
                ];
            }

            self::updateCampaign($existingId, $postId, $options);
            $campaignId = $existingId;
        } else {
            $campaignId = self::createCampaign($postId, $options);
            update_post_meta($postId, self::META_CAMPAIGN_ID, $campaignId);
        }

        self::applyRecipients($campaignId, $options['tag_ids'] ?? []);
        self::applySendSchedule($campaignId, $options);
        update_post_meta($postId, self::META_STATUS, self::getStatus($campaignId));

        return [
            'success'      => true,
            'message'      => '뉴스레터가 성공적으로 생성되었습니다.',
            'campaign_id'  => $campaignId,
            'campaign_url' => self::getCampaignUrl($campaignId),
            'status'       => self::getStatus($campaignId),
        ];
    }

    private static function createCampaign(int $postId, array $options): int {
        $title    = get_the_title($postId);
        $campaign = \FluentCrm\App\Models\Campaign::create([
            'title'           => sanitize_text_field($title),
            'status'          => 'draft',
            'email_subject'   => sanitize_text_field($title),
            'email_body'      => EmailTemplate::render($postId, $options['content_type'] ?? 'full'),
            'design_template' => 'raw_html',
        ]);

        return (int) $campaign->id;
    }

    private static function updateCampaign(int $campaignId, int $postId, array $options): void {
        $title    = get_the_title($postId);
        $campaign = \FluentCrm\App\Models\Campaign::find($campaignId);
        if (!$campaign) return;

        $campaign->fill([
            'title'         => sanitize_text_field($title),
            'email_subject' => sanitize_text_field($title),
            'email_body'    => EmailTemplate::render($postId, $options['content_type'] ?? 'full'),
        ])->save();
    }

    private static function applyRecipients(int $campaignId, array $tagIds): void {
        if (empty($tagIds)) return;

        $campaign = \FluentCrm\App\Models\Campaign::find($campaignId);
        if (!$campaign) return;

        $subscribers = array_map(
            fn(int $tagId) => ['list' => null, 'tag' => $tagId],
            $tagIds
        );

        $settings                   = (array) ($campaign->settings ?? []);
        $settings['subscribers']    = $subscribers;
        $settings['sending_filter'] = 'list_tag';
        $settings['excludedSubscribers'] = [['list' => null, 'tag' => null]];

        $campaign->settings = $settings;
        $campaign->save();
    }

    private static function applySendSchedule(int $campaignId, array $options): void {
        $sendType    = $options['send_type']    ?? 'now';
        $scheduledAt = $options['scheduled_at'] ?? '';

        if ($sendType === 'now') {
            \FluentCrm\App\Models\Campaign::where('id', $campaignId)->update([
                'status'       => 'processing',
                'scheduled_at' => current_time('mysql'),
            ]);
        } elseif ($sendType === 'scheduled' && $scheduledAt) {
            \FluentCrm\App\Models\Campaign::where('id', $campaignId)->update([
                'status'       => 'pending-scheduled',
                'scheduled_at' => sanitize_text_field($scheduledAt),
            ]);
        }
        // send_type === 'draft' 이면 상태 변경 없음 (FluentCRM UI에서 수동 발송)
    }

    public static function getStatus(int $campaignId): string {
        $campaign = \FluentCrm\App\Models\Campaign::find($campaignId);
        return $campaign ? (string) $campaign->status : '';
    }

    public static function getCampaignUrl(int $campaignId): string {
        return admin_url('admin.php?page=fluentcrm-admin#/campaigns/' . $campaignId . '/overview');
    }

    private static function campaignExists(int $campaignId): bool {
        return \FluentCrm\App\Models\Campaign::where('id', $campaignId)->exists();
    }
}
