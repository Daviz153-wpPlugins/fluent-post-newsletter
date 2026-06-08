# CLAUDE.md — Fluent Post Newsletter

## 프로젝트

워드프레스 포스트를 FluentCRM 이메일 캠페인으로 복제·발송하는 애드온 플러그인.
상세 스펙: `PROJECT.md`

## 환경

- PHP 8.2 / WordPress 7.0 / Gutenberg 블록 에디터
- 의존: FluentCRM (필수), FluentSMTP
- 배포: GitHub 릴리즈 + Plugin Update Checker 자동 업데이트

---

## 개발 5대 원칙

### 1. 코드는 최대한 간결하게

- **FluentCRM 리소스 우선** — API/훅/모델/스마트코드 최대 활용, 중복 구현 금지
- 기능 구현에 필요한 최소한의 코드만 작성
- 추상화는 동일 로직이 3회 이상 반복될 때만
- 미래를 위한 코드 작성 금지 (지금 필요한 것만)
- 이메일 HTML = 인라인 CSS만 (`<style>` 태그 금지)

### 2. 오류를 쉽게 찾을 수 있게

- 클래스 하나 = 역할 하나 (단일 책임 원칙)
- 실패는 `WP_Error` 또는 `['success'=>false, 'message'=>'구체적 이유']`로 명시 반환
- 조용한 실패(silent fail) 금지 — 어디서 무엇이 왜 실패했는지 메시지에 포함
- 발송된 캠페인 수정 시 코드 레벨에서 명확한 오류 반환 (`MODIFIABLE_STATUSES` 체크)
- 캠페인 ID는 생성 즉시 post_meta에 저장 — 미저장으로 인한 중복 생성 방지

### 3. 보안 강화

- 모든 PHP 파일 상단: `defined('ABSPATH') || exit;`
- AJAX 핸들러: `check_ajax_referer()` + `current_user_can()` 필수
- DB 쿼리: `$wpdb->prepare()` 필수
- 모든 출력: `esc_html()` / `esc_attr()` / `esc_url()` 적용
- 외부 입력값 전부 `sanitize_*()` 처리 후 사용
- 하드코딩된 URL/IP/비밀번호/API 키 절대 금지

### 4. UI/UX는 직관적으로

- 한국어 레이블/메시지 (비개발자 기준)
- 버튼 상태 피드백: 처리 중(비활성화) → 성공(녹색) / 실패(빨간색)
- 발송 후 FluentCRM 캠페인 직접 링크 제공
- 발송된 캠페인 수정 시도 시 이유를 명확히 안내
- 태그 없으면 "등록된 태그 없음" 안내 (빈 화면 금지)

### 5. 개인정보 GitHub 업로드 절대 금지

커밋 전 반드시 `git diff --staged` 확인:
- API 키, 비밀번호, 토큰이 포함되어 있지 않은가
- 사용자 이메일, 이름, 연락처가 포함되어 있지 않은가
- wp-config.php, .env 등 설정 파일이 포함되어 있지 않은가

`.gitignore` 필수 항목: `.env`, `*.log`, `/vendor/`, `wp-config.php`, `*-local.php`, `.DS_Store`, `node_modules/`, `*.zip`

---

## 플러그인별 절대 규칙

1. **FluentCRM 리소스 우선** — 발송/추적/수신거부/스케줄/대시보드 전부 FluentCRM에 위임
2. **발신자 정보 = FluentCRM 전역 설정** — 별도 발신자 UI 추가 금지
3. **발송된 캠페인 수정 절대 금지** — `sent`/`processing` 상태 = 코드 레벨 차단
4. **커스텀 DB 테이블 없음** — post_meta만 사용

---

## 파일 구조

```
fluent-post-newsletter/
├── fluent-post-newsletter.php      ← 메인 (의존성 체크, 자동업데이트)
├── includes/
│   ├── class-plugin.php            ← 부트스트랩
│   ├── class-meta-box.php          ← 포스트 편집 화면 UI + AJAX
│   ├── class-campaign-manager.php  ← FluentCRM 캠페인 생성/수정
│   ├── class-email-template.php    ← Ghost 스타일 이메일 HTML (인라인 CSS)
│   └── class-content-sanitizer.php ← 블록 HTML → 이메일 안전 HTML
├── assets/
│   ├── js/meta-box.js
│   └── css/meta-box.css
├── CLAUDE.md
└── PROJECT.md
```

---

## 이메일 렌더링 핵심 주의사항

블록 에디터 HTML → 이메일 변환 시 반드시 적용:
- **제거**: `<script>`, `<iframe>`, 블록 주석(`<!-- wp:... -->`), `wp-block-*` class
- **유지**: `p`, `h2~h4`, `ul/ol/li`, `a`, `img`, `blockquote`, `strong`, `em`
- **이미지**: 절대 URL 변환 + `max-width:100%; height:auto;` 인라인 강제 적용
- **테스트 대상**: Gmail, 네이버 메일, 카카오 메일, 모바일(iOS/Android)

## FluentCRM 호환성

- 클래스 사용 전 `class_exists()` 체크 필수
- 내부 클래스 직접 인스턴스화 최소화 → 공개 훅/API 우선
- FluentCRM 업데이트 시 호환성 테스트 후 이 플러그인도 업데이트
