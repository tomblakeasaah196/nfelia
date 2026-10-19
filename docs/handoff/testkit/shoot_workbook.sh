#!/usr/bin/env bash
# The home page's pictures of the Records Book itself: the demo shop's real
# workbook, open in a real spreadsheet program (LibreOffice Calc on a
# virtual screen), one picture per sheet shown, cropped and saved as WebP in
# assets/img/home/. The phone screens are shoot_records.js.
#
# Needs: the demo shop and its book (records_seed.php, then a book made the
# way shoot_records.js says), LibreOffice, Xvfb (xvfb-run), ImageMagick and
# Python 3.
#   bash docs/handoff/testkit/shoot_workbook.sh [work dir]
set -euo pipefail
root="$(cd "$(dirname "$0")/../../.." && pwd)"
work="${1:-$(mktemp -d)}"
mkdir -p "$work/profile/user" "$work/books"

row=$(mysql -N smartqaq_nfelia_core -e "SELECT CONCAT(file_path, '|', file_name) FROM records_books WHERE business_id = 50 AND kind = 'book' AND status = 'ready' ORDER BY id DESC LIMIT 1")
[ -n "$row" ] || { echo "Ivie has no Records Book: make one first (see shoot_records.js)." >&2; exit 2; }
book="$root/${row%%|*}"
name="${row##*|}"

# A first start with no tips, no "what's new", and no page-break lines.
cat > "$work/profile/user/registrymodifications.xcu" <<'XCU'
<?xml version="1.0" encoding="UTF-8"?>
<oor:items xmlns:oor="http://openoffice.org/2001/registry" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<item oor:path="/org.openoffice.Office.Common/Misc"><prop oor:name="ShowTipOfTheDay" oor:op="fuse"><value>false</value></prop></item>
<item oor:path="/org.openoffice.Office.Common/Misc"><prop oor:name="FirstRun" oor:op="fuse"><value>false</value></prop></item>
<item oor:path="/org.openoffice.Setup/Office"><prop oor:name="ooSetupInstCompleted" oor:op="fuse"><value>true</value></prop></item>
<item oor:path="/org.openoffice.Setup/Product"><prop oor:name="ooSetupLastVersion" oor:op="fuse"><value>99.0</value></prop></item>
<item oor:path="/org.openoffice.Office.Calc/Layout/Line"><prop oor:name="PageBreak" oor:op="fuse"><value>false</value></prop></item>
</oor:items>
XCU

# [sheet to show, zoom %, picture, crop WxH+X+Y of the 1600 × 1000 screen].
# No sheet with customers' phone numbers: the demo's are made up, but could
# be someone's.
shots=(
    "Cover|100|workbook-cover|1130x1000+0+0"
    "At a glance|100|workbook-glance|1130x1000+0+0"
    "Receipts|80|workbook-receipts|1130x1000+0+0"
)
for shot in "${shots[@]}"; do
    IFS='|' read -r sheet zoom picture crop <<<"$shot"
    dir="$work/books/$picture"
    mkdir -p "$dir"
    # The same file, opening on another sheet at another zoom: only how the
    # workbook opens changes, never what is in it.
    python3 - "$book" "$dir/$name" "$sheet" "$zoom" <<'PY'
import re, sys, zipfile
src, dst, want, zoom = sys.argv[1:5]
with zipfile.ZipFile(src) as z:
    names = re.findall(r'<sheet [^>]*name="([^"]+)"', z.read('xl/workbook.xml').decode())
    index = names.index(want.replace('&', '&amp;'))
    with zipfile.ZipFile(dst, 'w', zipfile.ZIP_DEFLATED) as out:
        for item in z.infolist():
            data = z.read(item.filename)
            if item.filename == 'xl/workbook.xml':
                data = re.sub(rb'activeTab="\d+"', b'activeTab="%d"' % index, data)
                if b'activeTab=' not in data:
                    data = data.replace(b'<workbookView ', b'<workbookView activeTab="%d" ' % index, 1)
            elif re.match(r'xl/worksheets/sheet\d+\.xml$', item.filename):
                n = int(re.findall(r'\d+', item.filename)[0]) - 1
                data = re.sub(rb' tabSelected="\d"', b'', data)
                if n == index:
                    data = re.sub(rb' zoomScale(Normal)?="\d+"', b'', data)
                    data = data.replace(b'<sheetView ', b'<sheetView tabSelected="1" zoomScale="%s" zoomScaleNormal="%s" ' % (zoom.encode(), zoom.encode()), 1)
            out.writestr(item, data)
PY
    timeout 120 xvfb-run -a -s "-screen 0 1600x1000x24" bash -c '
        soffice --nologo --norestore -env:UserInstallation=file://'"$work"'/profile --calc "'"$dir/$name"'" >/dev/null 2>&1 &
        sleep 25
        import -window root "'"$dir"'/screen.png"
        kill $! 2>/dev/null || true
        sleep 2
    ' || true
    pkill -f "UserInstallation=file://$work/profile" 2>/dev/null || true
    convert "$dir/screen.png" -crop "$crop" +repage -quality 82 -define webp:method=6 "$root/assets/img/home/$picture.webp"
    echo "$picture: $(identify -format '%wx%h %b' "$root/assets/img/home/$picture.webp")"
done
