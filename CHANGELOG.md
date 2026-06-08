# Changelog

## [0.3.1] - 2026-06-09

### 수정
- `<strong>/<b>` → `<span style="font-weight:800;">` 대체 — 모바일 이메일 클라이언트 볼드 호환성

---

## [0.3.0] - 2026-06-09

### 변경
- 본문 폰트 16px → 17px, 행간 1.7 → 1.9
- `<strong>/<b>` 에 `font-weight:700` 인라인 스타일 명시 (이메일 클라이언트 호환성)

### 수정
- `custom-highlight` CSS 클래스 → `background-color:#fef08a` 인라인 변환 (이메일에서 CSS 클래스 무효)
- Gutenberg underline(`text-decoration:underline`) → 동일 형광펜으로 변환
- GitHub 릴리즈 zip 자동 생성 Actions 워크플로우 추가

---

## [0.2.0] - 2026-06-08

### 추가
- Plugin Update Checker v5.7 — GitHub 릴리즈 기반 자동 업데이트
- `uninstall.php` — 플러그인 삭제 시 DB 데이터 클린 삭제
- E2E 테스트 21케이스 (`tests/e2e/run.sh`)

### 변경
- 메타박스 UI 단순화: 태그·발송타입·예약 폼 제거, draft 캠페인 생성에만 집중
- 이메일 레이아웃 개선
  - 이미지: 풀블리드(가장자리까지 전체 폭)
  - 테이블: 텍스트와 동일하게 좌우 20px 정렬
  - `<h2>` 앞 구분선(`<hr>`) 자동 삽입

### 수정
- `<figure>` 태그 기본 마진(`margin: 1em 40px`) 초기화 — 이미지·테이블 정렬 불일치 해결
- `<img>` HTML `width`/`height` 속성 제거, CSS만으로 크기 제어 — Outlook 등 이메일 클라이언트 호환성

---

## [0.1.0] - 2026-06-06

### 추가
- 포스트 편집 화면 메타박스 — FluentCRM 캠페인 생성·업데이트 버튼
- FluentCRM draft 캠페인 자동 생성 (제목·본문·대표이미지 포함)
- 발송 완료 캠페인 수정 차단 (`sent`/`processing` 상태 코드 레벨 차단)
- Ghost 스타일 이메일 템플릿 (인라인 CSS, 수신거부 링크)
- 블록 에디터 HTML → 이메일 안전 HTML 변환 (wp_kses 화이트리스트, 절대 URL, 테이블 지원)
- FluentCRM 비활성 시 관리자 알림 및 플러그인 자동 비활성화
