<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class EmailTemplate {

    public static function render(int $postId, string $contentType = 'full'): string {
        $post      = get_post($postId);
        $title     = get_the_title($postId);
        $postUrl   = get_permalink($postId);
        $authorId  = $post->post_author;
        $authorName = get_the_author_meta('display_name', $authorId);
        $date      = get_the_date('Y년 n월 j일', $postId);
        $siteName  = get_bloginfo('name');
        $siteDesc  = get_bloginfo('description');
        $thumbUrl  = get_the_post_thumbnail_url($postId, 'large');

        $headerText = $siteDesc ? $siteName . ' | ' . $siteDesc : $siteName;

        $body = self::buildBody($post, $contentType, $postUrl);

        $thumbHtml = '';
        if ($thumbUrl) {
            $thumbHtml = '<tr><td style="padding:0 0 24px 0;">'
                       . '<img src="' . esc_url($thumbUrl) . '" alt="" '
                       . 'style="width:100%;max-width:600px;height:auto;display:block;">'
                       . '</td></tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background-color:#f6f6f6;font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Noto Sans KR','Malgun Gothic',sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f6f6;">
  <tr>
    <td align="center" style="padding:24px 16px;">

      <table width="600" cellpadding="0" cellspacing="0" border="0"
             style="max-width:600px;width:100%;background-color:#ffffff;">

        <!-- 헤더: 사이트명 -->
        <tr>
          <td style="padding:32px 40px 0 40px;text-align:center;">
            <p style="margin:0;font-size:13px;color:#6b7280;letter-spacing:0.02em;">
              {$headerText}
            </p>
          </td>
        </tr>

        <!-- 포스트 제목 -->
        <tr>
          <td style="padding:20px 40px 8px 40px;text-align:center;">
            <h1 style="margin:0;font-size:30px;font-weight:700;line-height:1.35;color:#111827;letter-spacing:-0.01em;">
              {$title}
            </h1>
          </td>
        </tr>

        <!-- 작성자 · 날짜 · 브라우저에서 보기 -->
        <tr>
          <td style="padding:8px 40px 24px 40px;text-align:center;">
            <p style="margin:0;font-size:13px;color:#9ca3af;">
              {$authorName} 작성&nbsp;·&nbsp;{$date}
            </p>
            <p style="margin:6px 0 0 0;">
              <a href="{$postUrl}"
                 style="font-size:13px;color:#6b7280;text-decoration:underline;">
                브라우저에서 보기
              </a>
            </p>
          </td>
        </tr>

        <!-- 대표 이미지 -->
        {$thumbHtml}

        <!-- 본문 -->
        <tr>
          <td style="padding:0 40px 40px 40px;font-size:16px;line-height:1.7;color:#374151;">
            {$body}
          </td>
        </tr>

        <!-- 푸터 -->
        <tr>
          <td style="padding:24px 40px;border-top:1px solid #e5e7eb;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">
              <a href="{{crm.unsubscribe_url}}"
                 style="color:#9ca3af;text-decoration:underline;">
                수신 거부
              </a>
              &nbsp;·&nbsp;© {$siteName}
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;
    }

    private static function buildBody(\WP_Post $post, string $contentType, string $postUrl): string {
        if ($contentType === 'excerpt') {
            $excerpt = $post->post_excerpt
                ? $post->post_excerpt
                : wp_trim_words($post->post_content, 60, '...');

            $excerpt = ContentSanitizer::sanitize('<p>' . $excerpt . '</p>');

            return $excerpt
                . '<p style="margin:24px 0 0 0;text-align:center;">'
                . '<a href="' . esc_url($postUrl) . '" '
                . 'style="display:inline-block;padding:12px 28px;background-color:#111827;'
                . 'color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;'
                . 'border-radius:4px;" target="_blank" rel="noopener">'
                . '원문 읽기 →'
                . '</a></p>';
        }

        return ContentSanitizer::sanitize($post->post_content);
    }
}
