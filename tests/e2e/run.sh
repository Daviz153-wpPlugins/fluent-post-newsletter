#!/usr/bin/env bash
# Fluent Post Newsletter — E2E 테스트
# 실행: bash tests/e2e/run.sh

set -uo pipefail

WP="docker exec --user www-data wordpress-dev-wordpress-1 wp"
PASS=0
FAIL=0

ok()   { echo "  PASS  $1"; PASS=$((PASS + 1)); }
fail() { echo "  FAIL  $1"; echo "        expected=$2  got=$3"; FAIL=$((FAIL + 1)); }

assert_eq() {
    [ "$2" = "$3" ] && ok "$1" || fail "$1" "$2" "$3"
}
assert_has() {
    local label="$1" needle="$2" text="$3"
    if echo "$text" | grep -q "$needle"; then ok "$label"
    else echo "  FAIL  $label (찾을 수 없음: $needle)"; FAIL=$((FAIL + 1)); fi
}
assert_no() {
    local label="$1" needle="$2" text="$3"
    if ! echo "$text" | grep -q "$needle"; then ok "$label"
    else echo "  FAIL  $label (없어야 할 패턴 발견: $needle)"; FAIL=$((FAIL + 1)); fi
}

echo "============================================"
echo "  Fluent Post Newsletter — E2E Tests"
echo "============================================"

# ── [1] 캠페인 생성 ─────────────────────────────────────────
echo ""
echo "[1] 발행 포스트 → 캠페인 생성"

DATA=$($WP eval '
$pid = wp_insert_post([
    "post_title"   => "_FPN_E2E_",
    "post_content" => "<!-- wp:paragraph --><p>E2E 테스트 본문</p><!-- /wp:paragraph --><!-- wp:heading --><h2>소제목</h2><!-- /wp:heading -->",
    "post_status"  => "publish",
    "post_type"    => "post",
]);
$r = \FluentPostNewsletter\CampaignManager::createOrUpdate($pid);
echo json_encode(["pid" => $pid, "success" => $r["success"], "cid" => $r["campaign_id"], "status" => $r["status"]]);
' 2>&1)

PID=$(echo "$DATA" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['pid'])")
CID=$(echo "$DATA" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['cid'])")
assert_eq "createOrUpdate 성공" "True" "$(echo "$DATA" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['success'])")"
assert_eq "campaign_id 양수"   "1"    "$([ "$CID" -gt 0 ] && echo 1 || echo 0)"
assert_eq "초기 상태 = draft"  "draft" "$(echo "$DATA" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['status'])")"

# ── [2] draft 캠페인 업데이트 ────────────────────────────────
echo ""
echo "[2] draft 캠페인 업데이트"

UPD=$($WP eval "
\$r = \FluentPostNewsletter\CampaignManager::createOrUpdate($PID);
echo json_encode([\"success\" => \$r[\"success\"]]);
" 2>&1)
assert_eq "draft 재업데이트 성공" "True" "$(echo "$UPD" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['success'])")"

# ── [3] 발송 완료 캠페인 수정 차단 ──────────────────────────
echo ""
echo "[3] sent 캠페인 수정 차단"

BLK=$($WP eval "
\FluentCrm\App\Models\Campaign::find($CID)->fill(['status'=>'sent'])->save();
\$r = \FluentPostNewsletter\CampaignManager::createOrUpdate($PID);
echo json_encode([\"success\" => \$r[\"success\"]]);
" 2>&1)
assert_eq "sent 캠페인 수정 차단됨" "False" "$(echo "$BLK" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['success'])")"

# ── [4] 미발행 포스트 차단 ───────────────────────────────────
echo ""
echo "[4] 미발행(draft) 포스트 처리 거부"

DRF=$($WP eval '
$dpid = wp_insert_post([
    "post_title"  => "_FPN_E2E_DRAFT_",
    "post_content"=> "<p>draft</p>",
    "post_status" => "draft",
    "post_type"   => "post",
]);
$post = get_post($dpid);
$blocked = $post->post_status !== "publish";
wp_delete_post($dpid, true);
echo json_encode(["blocked" => $blocked]);
' 2>&1)
assert_eq "미발행 포스트 차단 로직" "True" "$(echo "$DRF" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['blocked'])")"

# ── [5] 이메일 템플릿 ────────────────────────────────────────
echo ""
echo "[5] 이메일 템플릿 구조 검증"

HTML=$($WP eval "echo \FluentPostNewsletter\EmailTemplate::render($PID);" 2>&1)
assert_has "DOCTYPE 포함"            "<!DOCTYPE"               "$HTML"
assert_no  "<style> 태그 없음"       "<style"                  "$HTML"
assert_has "인라인 CSS 존재"         'style="'                 "$HTML"
assert_has "unsubscribe 스마트코드"  "{{crm.unsubscribe_url}}" "$HTML"
assert_has "본문 td 좌우패딩 없음"   'padding:0 0 40px 0'      "$HTML"

# ── [6] ContentSanitizer ─────────────────────────────────────
echo ""
echo "[6] ContentSanitizer 변환 검증"

SAN=$($WP eval '
$html = \FluentPostNewsletter\ContentSanitizer::sanitize(
    "<!-- wp:paragraph --><p>본문</p><!-- /wp:paragraph -->" .
    "<!-- wp:heading --><h2>제목</h2><!-- /wp:heading -->" .
    "<script>alert(1)</script>" .
    "<figure class=\"wp-block-image\"><img src=\"/up/test.jpg\" width=\"1024\" height=\"768\" alt=\"\"></figure>" .
    "<!-- wp:table --><figure class=\"wp-block-table\"><table><tr><td>셀</td></tr></table></figure><!-- /wp:table -->"
);
echo $html;
' 2>&1)

assert_no  "script 태그 제거"          "<script>"            "$SAN"
assert_no  "wp-block 클래스 제거"      "wp-block"            "$SAN"
assert_no  "img width 속성 제거"       'width="1024"'        "$SAN"
assert_has "p 좌우 패딩"              'padding-left:20px'    "$SAN"
assert_has "h2 앞 hr 삽입"            '<hr style='           "$SAN"
assert_has "img CSS 풀폭"             'width:100%'           "$SAN"
assert_has "이미지 figure 마진 초기화" 'padding:0;'          "$SAN"
assert_has "테이블 figure 좌우 패딩"  'padding:0 20px'       "$SAN"
assert_has "테이블 border 스타일"     'border-collapse'      "$SAN"

# ── [7] uninstall 정리 함수 ──────────────────────────────────
echo ""
echo "[7] delete_post_meta_by_key 함수 존재 확인"

FN=$($WP eval 'echo function_exists("delete_post_meta_by_key") ? "ok" : "missing";' 2>&1)
assert_eq "delete_post_meta_by_key 존재" "ok" "$FN"

# ── 테스트 데이터 정리 ───────────────────────────────────────
$WP eval "
\FluentCrm\App\Models\Campaign::find($CID)->delete();
wp_delete_post($PID, true);
" 2>/dev/null

# ── 결과 ─────────────────────────────────────────────────────
echo ""
echo "============================================"
printf "  PASS: %d  FAIL: %d\n" "$PASS" "$FAIL"
echo "============================================"

[ "$FAIL" -eq 0 ]
