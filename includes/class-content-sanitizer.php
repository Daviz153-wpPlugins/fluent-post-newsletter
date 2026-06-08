<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class ContentSanitizer {

    // 이메일에서 허용할 태그와 속성
    private const ALLOWED_TAGS = [
        'p'          => ['style' => []],
        'h2'         => ['style' => []],
        'h3'         => ['style' => []],
        'h4'         => ['style' => []],
        'ul'         => ['style' => []],
        'ol'         => ['style' => []],
        'li'         => ['style' => []],
        'a'          => ['href' => [], 'style' => [], 'target' => [], 'rel' => []],
        'img'        => ['src' => [], 'alt' => [], 'style' => [], 'width' => [], 'height' => []],
        'blockquote' => ['style' => []],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'br'         => [],
        'hr'         => ['style' => []],
        'figure'     => ['style' => []],
        'figcaption' => ['style' => []],
    ];

    public static function sanitize(string $rawHtml): string {
        // 1. 블록 에디터 주석 제거 (<!-- wp:paragraph --> 등)
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/s', '', $rawHtml);

        // 2. the_content 필터 적용 (shortcode 처리 등)
        $html = apply_filters('the_content', $html);

        // 3. 허용 태그 화이트리스트 적용 (wp-block-* class 등 자동 제거)
        $html = wp_kses($html, self::ALLOWED_TAGS);

        // 4. 이미지 처리: 절대 URL 변환 + 이메일용 인라인 스타일
        $html = self::processImages($html);

        // 5. 링크에 target="_blank" 추가
        $html = preg_replace('/<a\s([^>]*href=[^>]*)>/i', '<a $1 target="_blank" rel="noopener">', $html);

        // 6. 빈 태그 정리
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);

        return trim($html);
    }

    private static function processImages(string $html): string {
        return preg_replace_callback(
            '/<img([^>]+)>/i',
            function (array $matches): string {
                $attrs = $matches[1];

                // src를 절대 URL로 변환
                $attrs = preg_replace_callback(
                    '/src=["\']([^"\']+)["\']/i',
                    function (array $m): string {
                        $url = $m[1];
                        if (!str_starts_with($url, 'http')) {
                            $url = site_url($url);
                        }
                        return 'src="' . esc_url($url) . '"';
                    },
                    $attrs
                );

                // 기존 style에 max-width 추가 (없으면 새로 생성)
                if (preg_match('/style=["\']([^"\']*)["\']/', $attrs, $styleMatch)) {
                    $style = rtrim($styleMatch[1], ';') . ';max-width:100%;height:auto;display:block;';
                    $attrs = preg_replace('/style=["\'][^"\']*["\']/', 'style="' . $style . '"', $attrs);
                } else {
                    $attrs .= ' style="max-width:100%;height:auto;display:block;"';
                }

                return '<img' . $attrs . '>';
            },
            $html
        );
    }
}
