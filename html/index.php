<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action =$_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $stmt =$pdo->prepare("INSERT INTO customers (name, phone, onu_type, port_number, package, notes, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],$_POST['phone'], $_POST['onu_type'],$_POST['port_number'], $_POST['package'],$_POST['notes'], 
            $_POST['lat'],$_POST['lng']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_cable') {
        $stmt =$pdo->prepare("INSERT INTO fiber_cables (cable_name, fiber_color, core_count, coordinates) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['cable_name'],$_POST['fiber_color'], $_POST['core_count'],$_POST['coordinates']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_manhole') {
        $stmt =$pdo->prepare("INSERT INTO manholes (mh_name, mh_type, lat, lng) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['mh_name'],$_POST['mh_type'], $_POST['lat'],$_POST['lng']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_item') {
        $type =$_POST['type'];
        $id =$_POST['id'];
        if ($type === 'customer') $stmt =$pdo->prepare("DELETE FROM customers WHERE id = ?");
        elseif ($type === 'cable') $stmt =$pdo->prepare("DELETE FROM fiber_cables WHERE id = ?");
        elseif ($type === 'manhole') $stmt =$pdo->prepare("DELETE FROM manholes WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

$customers =$pdo->query("SELECT * FROM customers")->fetchAll(PDO::FETCH_ASSOC);
$cables =$pdo->query("SELECT * FROM fiber_cables")->fetchAll(PDO::FETCH_ASSOC);
$manholes =$pdo->query("SELECT * FROM manholes")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة شبكات الفايبر الاحترافي (FTTH GIS)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8f9fa; overflow: hidden; }
        #map { height: calc(100vh - 70px); width: 100%; }
        .sidebar { height: calc(100vh - 70px); overflow-y: auto; background: white; box-shadow: -2px 0 5px rgba(0,0,0,0.1); z-index: 1000; }
        .card-custom { border-radius: 8px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 12px; }
        .nav-pills .nav-link { font-size: 13px; padding: 6px 10px; color: #495057; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark px-3" style="height: 70px;">
        <a class="navbar-brand fw-bold fs-5" href="#"><i class="fas fa-network-wired text-info"></i> Luxe ISP - Fiber GIS Pro</a>
        <div class="text-light">
            <span class="badge bg-primary me-2">الكابلات: <?= count($cables) ?></span>
            <span class="badge bg-success me-2">المشتركين: <?= count($customers) ?></span>
            <span class="badge bg-warning text-dark">المنهولات: <?= count($manholes) ?></span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- الخريطة -->
            <div class="col-lg-9 col-md-8 p-0">
                <div id="map"></div>
            </div>

            <!-- لوحة التحكم الجانبية -->
            <div class="col-lg-3 col-md-4 sidebar p-3">
                <ul class="nav nav-pills nav-fill mb-3 bg-light p-1 rounded" id="pills-tab" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-cust"><i class="fas fa-user"></i> مشترك</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-mh"><i class="fas fa-cube"></i> مانهول</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-list"><i class="fas fa-list"></i> القائمة</button></li>
                </ul>

                <div class="tab-content">
                    <!-- تبويب إضافة مشترك -->
                    <div class="tab-pane fade show active" id="tab-cust">
                        <div class="card card-custom p-3 bg-light">
                            <h6 class="text-success"><i class="fas fa-user-plus"></i> إضافة مشترك جديد</h6>
                            <form id="customer-form">
                                <div class="mb-2"><input type="text" id="cust-name" class="form-control form-control-sm" placeholder="اسم المشترك *" required></div>
                                <div class="mb-2"><input type="text" id="cust-phone" class="form-control form-control-sm" placeholder="رقم الهاتف"></div>
                                <div class="row mb-2">
                                    <div class="col-6"><input type="text" id="cust-onu" class="form-control form-control-sm" placeholder="نوع ONU"></div>
                                    <div class="col-6"><input type="text" id="cust-port" class="form-control form-control-sm" placeholder="رقم البورت"></div>
                                </div>
                                <div class="mb-2">
                                    <select id="cust-package" class="form-select form-select-sm">
                                        <option value="50 Mbps">باقة 50 Mbps</option>
                                        <option value="100 Mbps">باقة 100 Mbps</option>
                                        <option value="Dedicated">باقة Dedicated</option>
                                    </select>
                                </div>
                                <div class="mb-2"><textarea id="cust-notes" class="form-control form-control-sm" placeholder="ملاحظات أو العنوان التثليثي" rows="2"></textarea></div>
                                <button type="button" id="btn-add-cust" class="btn btn-outline-success btn-sm w-100"><i class="fas fa-map-marker-alt"></i> تحديد موقع المشترك بالخريطة</button>
                            </form>
                        </div>
                    </div>

                    <!-- تبويب إضافة مانهول / نقطة تفتيش -->
                    <div class="tab-pane fade" id="tab-mh">
                        <div class="card card-custom p-3 bg-light">
                            <h6 class="text-warning text-dark"><i class="fas fa-cube"></i> إضافة مانهول / Splitter</h6>
                            <form id="mh-form">
                                <div class="mb-2"><input type="text" id="mh-name" class="form-control form-control-sm" placeholder="اسم أو رمز المنهول (مثال: MH-10)" required></div>
                                <div class="mb-2">
                                    <select id="mh-type" class="form-select form-select-sm">
                                        <option value="Manhole">Manhole (منهول أرضي)</option>
                                        <option value="Splitter">Optical Splitter (مقسم)</option>
                                        <option value="ODF">ODF (نقطة رئيسية)</option>
                                    </select>
                                </div>
                                <button type="button" id="btn-add-mh" class="btn btn-outline-warning text-dark btn-sm w-100"><i class="fas fa-map-marker-alt"></i> تحديد موقع المنهول بالخريطة</button>
                            </form>
                        </div>
                    </div>

                    <!-- تبويب القوائم -->
                    <div class="tab-pane fade" id="tab-list">
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" id="search-box" class="form-control" placeholder="بحث سريع...">
                        </div>
                        <div id="all-records-list" style="max-height: 400px; overflow-y: auto;"></div>
                    </div>
                </div>

                <hr>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle"></i> <b>أدوات الرسم:</b> استخدم أداة الخط من يسار الخريطة لرسم مسار فايبر جديد واختيار لونه.
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const map = L.map('map').setView([36.1900, 44.0090], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map);

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            edit: { featureGroup: drawnItems },
            draw: { polygon: false, circle: false, rectangle: false, marker: false, circlemarker: false, polyline: { shapeOptions: { color: '#0dcaf0', weight: 4 } } }
        });
        map.addControl(drawControl);

        let activeMode = null; // 'customer' or 'manhole'
        const customers = <?= json_encode($customers) ?>;
        const cables = <?= json_encode($cables) ?>;
        const manholes = <?= json_encode($manholes) ?>;

        // تفعيل أوضاع الإضافة
        document.getElementById('btn-add-cust').addEventListener('click', () => {
            if(!document.getElementById('cust-name').value) { alert('أدخل اسم المشترك أولاً!'); return; }
            activeMode = 'customer';
            alert('انقر الآن على موقع المشترك في الخريطة');
        });

        document.getElementById('btn-add-mh').addEventListener('click', () => {
            if(!document.getElementById('mh-name').value) { alert('أدخل اسم المنهول أولاً!'); return; }
            activeMode = 'manhole';
            alert('انقر الآن على موقع المنهول في الخريطة');
        });

        // رسم المشتركين
        customers.forEach(cust => {
            const icon = L.divIcon({
                html: `<div style="background:#198754; color:white; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-user" style="font-size:12px;"></i></div>`,
                iconSize: [28, 28], iconAnchor: [14, 14]
            });
            L.marker([cust.lat, cust.lng], {icon}).addTo(map)
             .bindPopup(`<b>مشترك:</b> ${cust.name}<br><b>الهاتف:</b> ${cust.phone || '-'}<br><b>ONU:</b> ${cust.onu_type || '-'}<br><b>بورت:</b> ${cust.port_number || '-'}<br><b>باقة:</b> ${cust.package}<br><b>ملاحظات:</b> ${cust.notes || '-'}<br><button class="btn btn-danger btn-sm mt-2" onclick="deleteItem('customer', ${cust.id})">حذف المشترك</button>`);
        });

        // رسم المنهولات
        manholes.forEach(mh => {
            const color = mh.mh_type === 'ODF' ? '#dc3545' : (mh.mh_type === 'Splitter' ? '#ffc107' : '#fd7e14');
            const icon = L.divIcon({
                html: `<div style="background:${color}; color:white; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-cube" style="font-size:12px;"></i></div>`,
                iconSize: [28, 28], iconAnchor: [14, 14]
            });
            L.marker([mh.lat, mh.lng], {icon}).addTo(map)
             .bindPopup(`<b>العقدة:</b> ${mh.mh_name}<br><b>النوع:</b> ${mh.mh_type}<br><button class="btn btn-danger btn-sm mt-2" onclick="deleteItem('manhole', ${mh.id})">حذف العقدة</button>`);
        });

        // رسم الكابلات المخزنة بألوانها
        cables.forEach(cable => {
            let latlngs = JSON.parse(cable.coordinates);
            L.polyline(latlngs, { color: cable.fiber_color || '#0dcaf0', weight: 4, dashArray: '5, 5' })
             .bindPopup(`<b>مسار فايبر:</b> ${cable.cable_name}<br><b>عدد الكور:</b> ${cable.core_count}<br><button class="btn btn-danger btn-sm mt-2" onclick="deleteItem('cable', ${cable.id})">حذف المسار</button>`)
             .addTo(drawnItems);
        });

        // النقر على الخريطة لتثبيت الموقع
        map.on('click', function(e) {
            if (!activeMode) return;

            if (activeMode === 'customer') {
                const data = new URLSearchParams({
                    action: 'save_customer',
                    name: document.getElementById('cust-name').value,
                    phone: document.getElementById('cust-phone').value,
                    onu_type: document.getElementById('cust-onu').value,
                    port_number: document.getElementById('cust-port').value,
                    package: document.getElementById('cust-package').value,
                    notes: document.getElementById('cust-notes').value,
                    lat: e.latlng.lat, lng: e.latlng.lng
                });
                postData(data);
            } else if (activeMode === 'manhole') {
                const data = new URLSearchParams({
                    action: 'save_manhole',
                    mh_name: document.getElementById('mh-name').value,
                    mh_type: document.getElementById('mh-type').value,
                    lat: e.latlng.lat, lng: e.latlng.lng
                });
                postData(data);
            }
            activeMode = null;
        });

        // رسم كابل فايبر جديد مع اختيار لونه وعدد الكورات
        map.on(L.Draw.Event.CREATED, function (e) {
            if (e.layerType === 'polyline') {
                const cableName = prompt("أدخل اسم مسار الكابل (مثال: Core-A-Route):", "Fiber Line");
                if (cableName) {
                    const color = prompt("اختر لون المسار (اكتب كود اللون، مثلاً: #0d6efd للأزرق، #dc3545 للأحمر، #ffc107 للأصفر):", "#0d6efd");
                    const cores = prompt("عدد الكورات في الكابل (مثلاً: 12 أو 24):", "24");
                    
                    const data = new URLSearchParams({
                        action: 'save_cable',
                        cable_name: cableName,
                        fiber_color: color || '#0dcaf0',
                        core_count: cores || 24,
                        coordinates: JSON.stringify(e.layer.getLatLngs())
                    });
                    postData(data);
                }
            }
        });

        function postData(data) {
            fetch('', { method: 'POST', body: data })
            .then(res => res.json())
            .then(res => { if(res.status === 'success') location.reload(); });
        }

        function deleteItem(type, id) {
            if(confirm('هل أنت متأكد من الحذف؟')) {
                const data = new URLSearchParams({ action: 'delete_item', type: type, id: id });
                postData(data);
            }
        }
    </script>
</body>
</html>
