<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// 모든 포스트에서 플러그인이 저장한 캠페인 ID 메타 삭제
delete_post_meta_by_key('_fpn_campaign_id');
