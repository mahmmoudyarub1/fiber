<?php
require_once 'db.php';

// معالجة طلبات الإدخال أو الحذف عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action =$_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $stmt =$pdo->prepare("INSERT INTO customers (name, phone, speed, lat, lng) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['phone'],$_POST['speed'], $_POST['lat'],$_POST['lng']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_cable') {
        $stmt =$pdo->prepare("INSERT INTO fiber_cables (cable_name, coordinates) VALUES (?, ?)");
        $stmt->execute([$_POST['cable_name'],$_POST['coordinates']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_customer') {
        $stmt =$pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

$customers =$pdo->query("SELECT * FROM customers")->fetchAll(PDO::FETCH_ASSOC);
$cables =$pdo->query("SELECT * FROM fiber_cables")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام GIS لإدارة شبكات الفايبر الأوبتيك</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8f9fa; }
        #map { height: calc(100vh - 70px); width: 100%; }
        .sidebar { height: calc(100vh - 70px); overflow-y: auto; background: white; box-shadow: -2px 0 5px rgba(0,0,0,0.1); z-index: 1000; }
        .card-custom { border-radius: 10px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 15px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark px-3" style="height: 70px;">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-network-wired text-info"></i> FTTH GIS Network System</a>
        <div class="text-light">
            <span class="badge bg-primary me-2">الكابلات: <?= count($cables) ?></span>
            <span class="badge bg-success">المشتركين: <?= count($customers) ?></span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-9 col-md-8 p-0">
                <div id="map"></div>
            </div>
            <div class="col-lg-3 col-md-4 sidebar p-3">
                <h5 class="mb-3 text-secondary border-bottom pb-2"><i class="fas fa-tools"></i> أدوات الإدارة</h5>
                <div class="card card-custom p-3 bg-light">
                    <h6><i class="fas fa-user-plus text-success"></i> إضافة مشترك جديد</h6>
                    <form id="customer-form">
                        <div class="mb-2">
                            <label class="form-label small">اسم المشترك:</label>
                            <input type="text" id="cust-name" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">رقم الهاتف / البانل:</label>
                            <input type="text" id="cust-phone" class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">السرعة / البورت:</label>
                            <input type="text" id="cust-speed" class="form-control form-control-sm" placeholder="50Mbps / Port 4">
                        </div>
                        <button type="button" id="enable-add-cust" class="btn btn-outline-success btn-sm w-100"><i class="fas fa-map-pin"></i> تفعيل وضع إضافة مشترك</button>
                    </form>
                </div>

                <h5 class="mb-2 text-secondary border-bottom pb-2 mt-4"><i class="fas fa-list"></i> قائمة المشتركين</h5>
                <div id="records-list" style="max-height: 280px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script>
        const map = L.map('map').setView([36.3400, 43.1300], 14); // مركز الموصل الافتراضي
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map);

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            edit: { featureGroup: drawnItems },
            draw: { polygon: false, circle: false, rectangle: false, marker: false, circlemarker: false, polyline: { shapeOptions: { color: '#0dcaf0', weight: 4, dashArray: '5, 5' } } }
        });
        map.addControl(drawControl);

        let addCustomerMode = false;
        const customers = <?= json_encode($customers) ?>;
        const cables = <?= json_encode($cables) ?>;

        document.getElementById('enable-add-cust').addEventListener('click', function() {
            if(!document.getElementById('cust-name').value) { alert('أدخل اسم المشترك أولاً!'); return; }
            addCustomerMode = true;
            this.className = 'btn btn-success btn-sm w-100';
            this.innerText = 'انقر على الخريطة لتحديد الموقع...';
        });

        customers.forEach(cust => addCustomerToMap(cust));
        updateSidebarList();

        cables.forEach(cable => {
            let latlngs = JSON.parse(cable.coordinates);
            L.polyline(latlngs, { color: '#0dcaf0', weight: 4, dashArray: '5, 5' })
             .bindPopup(`<b>مسار فايبر:</b> ${cable.cable_name}`)
             .addTo(drawnItems);
        });

        map.on('click', function(e) {
            if (!addCustomerMode) return;
            const data = new URLSearchParams({
                action: 'save_customer',
                name: document.getElementById('cust-name').value,
                phone: document.getElementById('cust-phone').value,
                speed: document.getElementById('cust-speed').value,
                lat: e.latlng.lat,
                lng: e.latlng.lng
            });
            fetch('', { method: 'POST', body: data }).then(res => res.json()).then(r => { if(r.status === 'success') location.reload(); });
        });

        map.on(L.Draw.Event.CREATED, function (e) {
            if (e.layerType === 'polyline') {
                const cableName = prompt("أدخل اسم مسار الكابل:", "Fiber Core");
                if (cableName) {
                    const data = new URLSearchParams({
                        action: 'save_cable',
                        cable_name: cableName,
                        coordinates: JSON.stringify(e.layer.getLatLngs())
                    });
                    fetch('', { method: 'POST', body: data }).then(res => res.json()).then(r => { if(r.status === 'success') location.reload(); });
                }
            }
        });

        function addCustomerToMap(cust) {
            const customIcon = L.divIcon({
                html: `<div style="background:#198754; color:white; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-user" style="font-size:11px;"></i></div>`,
                iconSize: [26, 26], iconAnchor: [13, 13]
            });
            L.marker([cust.lat, cust.lng], {icon: customIcon}).addTo(map)
             .bindPopup(`<b>مشترك:</b> ${cust.name}<br><b>الهاتف:</b> ${cust.phone}<br><b>السرعة:</b> ${cust.speed}<br><button class="btn btn-danger btn-sm mt-2" onclick="deleteCust(${cust.id})">حذف المشترك</button>`);
        }

        function updateSidebarList() {
            const list = document.getElementById('records-list');
            list.innerHTML = '';
            customers.forEach(cust => {
                list.innerHTML += `<div class="p-2 border-bottom bg-white mb-1 rounded d-flex justify-content-between align-items-center">
                    <div><span class="fw-bold text-success" style="font-size:13px;">${cust.name}</span><br><small class="text-muted">${cust.speed || ''}</small></div>
                    <button class="btn btn-sm btn-light text-primary" onclick="map.setView([${cust.lat}, ${cust.lng}], 18)"><i class="fas fa-crosshairs"></i></button>
                </div>`;
            });
        }

        function deleteCust(id) {
            if(confirm('هل تريد حذف هذا المشترك؟')) {
                const data = new URLSearchParams({ action: 'delete_customer', id: id });
                fetch('', { method: 'POST', body: data }).then(() => location.reload());
            }
        }
    </script>
</body>
</html>
