// Dữ liệu mẫu ban đầu phục vụ Demo
const pitchesData = [
    { id: 'SAN01', name: 'Sân 1A (5 người)', status: 'free', price: '300.000đ/h' },
    { id: 'SAN02', name: 'Sân 1B (5 người)', status: 'busy', price: '300.000đ/h' },
    { id: 'SAN03', name: 'Sân 2A (7 người)', status: 'free', price: '500.000đ/h' },
    { id: 'SAN04', name: 'Sân 2B (7 người)', status: 'busy', price: '500.000đ/h' },
    { id: 'SAN05', name: 'Sân 3A (11 người)', status: 'free', price: '1.000.000đ/h' },
    { id: 'SAN06', name: 'Sân 3B (11 người)', status: 'free', price: '1.000.000đ/h' }
];

const bookingsData = [
    { id: '#DS01', customer: 'Anh Nam (0901234567)', pitch: 'Sân 1B (5 người)', time: '17:30 - 19:00', status: 'Đang đá' },
    { id: '#DS02', customer: 'Anh Tuấn (0987654321)', pitch: 'Sân 2B (7 người)', time: '18:00 - 19:30', status: 'Đang đá' }
];

// Khởi chạy khi mở trang
document.addEventListener('DOMContentLoaded', () => {
    renderPitchStatus();
    renderBookingList();
});

// Hàm chuyển đổi giữa các Tab trên thanh Sidebar
function switchTab(tabId, element) {
    // Ẩn tất cả các tab
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    // Bỏ trạng thái active ở menu
    document.querySelectorAll('.nav-links li').forEach(li => {
        li.classList.remove('active');
    });

    // Hiển thị tab được chọn
    document.getElementById(tabId).classList.add('active');
    element.classList.add('active');
}

// Render các ô trạng thái sân bóng ở Trang chính
function renderPitchStatus() {
    const container = document.getElementById('pitch-status-container');
    container.innerHTML = pitchesData.map(p => `
        <div class="pitch-box ${p.status === 'free' ? 'is-free' : 'is-busy'}">
            <strong>${p.name}</strong>
            <span style="font-size: 13px; color: #64748b;">Giá: ${p.price}</span>
            <div>
                <span class="status-badge ${p.status}">
                    ${p.status === 'free' ? 'Đang trống' : 'Đang có khách'}
                </span>
            </div>
        </div>
    `).join('');
}

// Render danh sách đặt sân trong ngày
function renderBookingList() {
    const tbody = document.getElementById('booking-list-body');
    tbody.innerHTML = bookingsData.map(b => `
        <tr>
            <td><strong>${b.id}</strong></td>
            <td>${b.customer}</td>
            <td>${b.pitch}</td>
            <td>${b.time}</td>
            <td><span class="status-badge booked">${b.status}</span></td>
        </tr>
    `).join('');
}

// Tính toán tự động số tiền tạm tính trên Form Đặt Sân
function calculateEstimatedTotal() {
    const pitchSelect = document.getElementById('bookPitch');
    const pitchPrice = parseInt(pitchSelect.options[pitchSelect.selectedIndex].getAttribute('data-price')) || 0;
    
    let serviceTotal = 0;
    document.querySelectorAll('.srv-check:checked').forEach(chk => {
        serviceTotal += parseInt(chk.value);
    });

    const total = pitchPrice + serviceTotal;
    document.getElementById('estimatedTotal').innerText = total.toLocaleString('vi-VN') + ' đ';
}

// Xử lý sự kiện khi bấm nút "Xác Nhận Đặt Sân"
function handleBookingSubmit(event) {
    event.preventDefault();
    const customer = document.getElementById('bookCustomer').value;
    const pitch = document.getElementById('bookPitch').value;
    const start = document.getElementById('startTime').value;
    const end = document.getElementById('endTime').value;

    const newBooking = {
        id: '#DS0' + (bookingsData.length + 1),
        customer: customer,
        pitch: pitch,
        time: `${start} - ${end}`,
        status: 'Mới đặt sân'
    };

    bookingsData.unshift(newBooking);
    renderBookingList();
    showToast(`Đặt sân thành công cho ${customer}!`);
    event.target.reset();
    calculateEstimatedTotal();
}

// Thêm Sân Bóng Mới vào bảng Demo
function handleAddPitch(event) {
    event.preventDefault();
    const id = document.getElementById('newPitchId').value;
    const name = document.getElementById('newPitchName').value;
    const type = document.getElementById('newPitchType').value;
    const price = parseInt(document.getElementById('newPitchPrice').value).toLocaleString('vi-VN') + ' đ';

    const tbody = document.getElementById('pitch-table-body');
    tbody.innerHTML += `
        <tr>
            <td>${id}</td>
            <td>${name}</td>
            <td>${type}</td>
            <td>${price}</td>
            <td><span class="status-badge free">Đang trống</span></td>
        </tr>
    `;
    showToast(`Đã thêm sân ${name} thành công!`);
    event.target.reset();
}

// Thêm Khách Hàng Mới vào bảng Demo
function handleAddCustomer(event) {
    event.preventDefault();
    const id = document.getElementById('custId').value;
    const name = document.getElementById('custName').value;
    const phone = document.getElementById('custPhone').value;
    const address = document.getElementById('custAddress').value;

    const tbody = document.getElementById('customer-table-body');
    tbody.innerHTML += `
        <tr>
            <td>${id}</td>
            <td>${name}</td>
            <td>${phone}</td>
            <td>${address}</td>
        </tr>
    `;
    showToast(`Đã lưu thông tin khách hàng ${name}!`);
    event.target.reset();
}

// Thêm Dịch Vụ Mới vào bảng Demo
function handleAddService(event) {
    event.preventDefault();
    const id = document.getElementById('srvId').value;
    const name = document.getElementById('srvName').value;
    const unit = document.getElementById('srvUnit').value;
    const price = parseInt(document.getElementById('srvPrice').value).toLocaleString('vi-VN') + ' đ';

    const tbody = document.getElementById('service-table-body');
    tbody.innerHTML += `
        <tr>
            <td>${id}</td>
            <td>${name}</td>
            <td>${unit}</td>
            <td>${price}</td>
        </tr>
    `;
    showToast(`Đã thêm dịch vụ ${name}!`);
    event.target.reset();
}

// Hàm hiển thị thông báo nổi góc trên bên phải
function showToast(message) {
    const toast = document.getElementById('toast');
    toast.innerText = message;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}