# Fluent Post Newsletter — Claude 작업 지침

## 프로젝트

워드프레스 포스트를 FluentCRM 이메일 캠페인으로 복제·발송하는 애드온 플러그인.
상세 스펙: `PROJECT.md`

## 환경

- PHP 8.2 / WordPress 7.0 / Gutenberg 블록 에디터
- 의존: FluentCRM (필수), FluentSMTP
- 배포: GitHub 릴리즈 + 자동 업데이트

## 절대 규칙

1. **FluentCRM 리소스 우선** — FluentCRM API/훅/모델/스마트코드 최대 활용. 중복 구현 금지.
2. **이메일 HTML = 인라인 CSS만** — `<style>` 태그 금지. 모든 스타일은 인라인.
3. **발송된 캠페인 수정 금지** — `sent`/`processing` 상태 캠페인 업데이트 코드 레벨 차단.
4. **캠페인 ID 즉시 저장** — 생성 즉시 post_meta에 저장. 예외 없음.
5. **발신자 정보 = FluentCRM 전역 설정** — 별도 발신자 UI 추가 금지.

## 개발 원칙

- **간결함**: 불필요한 코드 없음. 추상화는 3회 이상 반복될 때만.
- **디버깅 용이**: 클래스 하나에 역할 하나. 책임 명확히 분리.
- **보안 최우선**: 모든 입력 검증/이스케이프. Nonce 없는 AJAX 요청 불가.
- **직관적 UI**: 한국어 레이블/메시지. 비개발자도 설명 없이 사용 가능해야 함.

## 보안 체크리스트 (코드 작성 시 매번)

- [ ] 모든 PHP 파일 상단: `defined('ABSPATH') || exit;`
- [ ] AJAX 핸들러: `check_ajax_referer()` 호출
- [ ] DB 쿼리: `$wpdb->prepare()` 사용
- [ ] 출력: `esc_html()`, `esc_attr()`, `esc_url()` 적용
- [ ] 하드코딩된 URL/IP/비밀번호 없음

## GitHub 업로드 금지 항목

`.gitignore`에 반드시 포함:
```
.env
*.log
/vendor/
wp-config.php
*-local.php
.DS_Store
node_modules/
```

커밋 전 `git diff --staged`로 개인정보 포함 여부 확인 필수.

## 파일 구조

```
fluent-post-newsletter/
├── fluent-post-newsletter.php      # 메인 (의존성 체크, 훅 등록)
├── includes/
│   ├── class-plugin.php            # 부트스트랩
│   ├── class-meta-box.php          # 포스트 편집 화면 UI
│   ├── class-campaign-manager.php  # FluentCRM 캠페인 생성/수정
│   ├── class-email-template.php    # Ghost 스타일 이메일 HTML
│   └── class-content-sanitizer.php # 블록 HTML → 이메일 안전 HTML
├── assets/
│   ├── js/meta-box.js              # AJAX + UI 인터랙션
│   └── css/meta-box.css            # 메타박스 스타일
├── languages/
├── CLAUDE.md
└── PROJECT.md
```

## 이메일 렌더링 핵심 (최우선 우려사항)

블록 에디터 HTML → 이메일 변환 시 반드시 적용:
- **제거**: `<script>`, `<iframe>`, 블록 주석(`<!-- wp:... -->`), `wp-block-*` class
- **유지**: `p`, `h2~h4`, `ul/ol/li`, `a`, `img`, `blockquote`, `strong`, `em`
- **이미지**: 절대 URL 변환 + `max-width:100%; height:auto;` 인라인 강제 적용
- **테스트 대상**: Gmail, 네이버 메일, 카카오 메일, 모바일(iOS/Android)

## FluentCRM 호환성

- 클래스 사용 전 `class_exists()` 체크 필수
- 내부 클래스 직접 인스턴스화 최소화 → 공개 훅/API 우선
- FluentCRM 버전 업데이트 시 이 플러그인도 호환성 테스트 후 업데이트
