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
        // 테이블
        'table'   => ['style' => [], 'width' => [], 'cellpadding' => [], 'cellspacing' => [], 'border' => []],
        'thead'   => ['style' => []],
        'tbody'   => ['style' => []],
        'tr'      => ['style' => []],
        'th'      => ['style' => [], 'colspan' => [], 'rowspan' => [], 'scope' => []],
        'td'      => ['style' => [], 'colspan' => [], 'rowspan' => []],
        'caption' => ['style' => []],
    ];

    public static function sanitize(string $rawHtml): string {
        // 1. 블록 에디터 주석 제거 (<!-- wp:paragraph --> 등)
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/s', '', $rawHtml);

        // 2. the_content 필터 적용 (shortcode 처리 등)
        $html = apply_filters('the_content', $html);

        // 3. 허용 태그 화이트리스트 적용 (wp-block-* class 등 자동 제거)
        $html = wp_kses($html, self::ALLOWED_TAGS);

        // 4. 텍스트 요소에 좌우 패딩 추가
        $html = self::processTextPadding($html);

        // 5. 이미지 처리: 절대 URL 변환 + 이메일용 인라인 스타일
        $html = self::processImages($html);

        // 6. 테이블 처리: 이메일용 인라인 스타일 강제 적용
        $html = self::processTables($html);

        // 7. figure 마진 초기화: 이미지 figure는 풀폭, 테이블 figure는 텍스트와 맞춤(좌우 20px)
        $html = preg_replace_callback(
            '/<figure(\s[^>]*|)>(.*?)<\/figure>/is',
            function (array $m): string {
                $inner   = $m[2];
                $padding = stripos($inner, '<table') !== false ? '0 20px' : '0';
                return "<figure style=\"margin:0;padding:{$padding};display:block;\">{$inner}</figure>";
            },
            $html
        );

        // 8. 링크에 target="_blank" 추가
        $html = preg_replace('/<a\s([^>]*href=[^>]*)>/i', '<a $1 target="_blank" rel="noopener">', $html);

        // 9. 빈 태그 정리
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);

        return trim($html);
    }

    private static function processTextPadding(string $html): string {
        // p, h2~h4, blockquote, figcaption: 좌우 20px 패딩 (텍스트를 컨테이너 가장자리에서 들여쓰기)
        $html = preg_replace_callback(
            '/<(p|h2|h3|h4|blockquote|figcaption)(\s[^>]*|)>/i',
            function (array $m): string {
                $tag   = strtolower($m[1]);
                $attrs = preg_replace('/style=["\'][^"\']*["\']/i', '', $m[2]);
                $style = 'padding-left:20px;padding-right:20px;';
                if ($tag === 'blockquote') {
                    $style .= 'border-left:3px solid #e5e7eb;margin:0;padding-top:4px;padding-bottom:4px;';
                }
                $prefix = $tag === 'h2'
                    ? '<hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 20px 24px 20px;">'
                    : '';
                return $prefix . "<{$tag}{$attrs} style=\"{$style}\">";
            },
            $html
        );

        // ul, ol: 좌우 마진으로 텍스트와 맞춤 (불릿 포함 20px 들여쓰기)
        $html = preg_replace_callback(
            '/<(ul|ol)(\s[^>]*|)>/i',
            function (array $m): string {
                $tag   = strtolower($m[1]);
                $attrs = preg_replace('/style=["\'][^"\']*["\']/i', '', $m[2]);
                return "<{$tag}{$attrs} style=\"margin:0 20px 16px 20px;padding-left:20px;\">";
            },
            $html
        );

        return $html;
    }

    private static function processTables(string $html): string {
        // table: 전체 폭, 테두리 병합
        $html = preg_replace_callback('/<table([^>]*)>/i', function (array $m): string {
            $attrs = preg_replace('/style=["\'][^"\']*["\']/i', '', $m[1]);
            return '<table' . $attrs . ' style="border-collapse:collapse;width:100%;margin:16px 0;font-size:15px;" cellpadding="0" cellspacing="0">';
        }, $html);

        // th: 배경색 + 굵게 + 테두리 + 패딩 (thead/tbody 제외)
        $html = preg_replace_callback('/<th(\s[^>]*|)>/i', function (array $m): string {
            $attrs = preg_replace('/style=["\'][^"\']*["\']/i', '', $m[1]);
            return '<th' . $attrs . ' style="border:1px solid #d1d5db;padding:10px 16px;background:#f3f4f6;font-weight:700;text-align:left;color:#111827;font-size:14px;">';
        }, $html);

        // td: 테두리 + 패딩
        $html = preg_replace_callback('/<td(\s[^>]*|)>/i', function (array $m): string {
            $attrs = preg_replace('/style=["\'][^"\']*["\']/i', '', $m[1]);
            return '<td' . $attrs . ' style="border:1px solid #d1d5db;padding:10px 16px;vertical-align:top;color:#374151;font-size:14px;line-height:1.6;">';
        }, $html);

        return $html;
    }

    private static function processImages(string $html): string {
        return preg_replace_callback(
            '/<img([^>]+)>/i',
            function (array $matches): string {
                $attrs = rtrim($matches[1], '/ '); // XHTML 자기닫기 슬래시 제거

                // width/height HTML 속성 제거 (CSS만으로 크기 제어 — Outlook 등 호환성)
                $attrs = preg_replace('/\s*(width|height)=["\'][^"\']*["\']/i', '', $attrs);

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

                // style 속성 교체: 풀폭(width:100%) + max-width + 높이 자동
                $attrs = preg_replace('/style=["\'][^"\']*["\']/', '', $attrs);
                $attrs .= ' style="width:100%;max-width:100%;height:auto;display:block;"';

                return '<img' . $attrs . '>';
            },
            $html
        );
    }
}
