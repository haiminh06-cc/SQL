'use strict';
(() => {
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const node = (tag, text, cls) => {
        const el = document.createElement(tag);
        if (text !== undefined && text !== null) el.textContent = String(text);
        if (cls) el.className = cls;
        return el;
    };
    const api = document.body.dataset.api;
    let data = null;
    let busy = false;
    let loading = false;
    let available = false;
    let rules = JSON.parse($('#boot').dataset.rules);
    let csrf = $('#boot input[name="_csrf"]').value;
    let opened = null;
    const money = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 2 }) + ' đ';
    const code = (prefix, id) => prefix + String(id).padStart(2, '0');
    const date = value => {
        if (!value) return '—';
        const parts = String(value).split(' ');
        return parts[0].split('-').reverse().join('/') + (parts[1] ? ' ' + parts[1].slice(0, 5) : '');
    };
    // Booking inputs are wall-clock times of the configured Vietnam venue, not the browser timezone.
    const minute = value => {
        const match = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(value || '');
        return match ? Date.UTC(...match.slice(1).map((n, i) => Number(n) - (i === 1 ? 1 : 0))) / 60000 : NaN;
    };
    const venueNow = () => {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
        const get = name => parts.find(p => p.type === name).value;
        return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`;
    };
    function badge(text, cls = 'neutral') { return node('span', text, 'status-badge ' + cls); }
    function bookingState(value) {
        const map = { [rules.booking_new]: ['Đã đặt', 'booked'], [rules.booking_received]: ['Đang sử dụng', 'busy'], [rules.booking_cancelled]: ['Đã hủy', 'neutral'], [rules.booking_completed]: ['Hoàn thành', 'free'] };
        const item = map[value] || [value, 'neutral'];
        return badge(...item);
    }
    function invoiceState(value) {
        return value === rules.invoice_paid ? badge('Đã thanh toán', 'free') : value === rules.invoice_unpaid ? badge('Chưa thanh toán', 'booked') : badge(value);
    }
    function pitchState(value) {
        return badge(...({ free: ['Đang trống', 'free'], busy: ['Đang có khách', 'busy'], maintenance: ['Bảo trì / ngừng hoạt động', 'booked'] }[value] || ['Chưa rõ trạng thái', 'neutral']));
    }
    function notify(message, kind = 'info') {
        const box = $('#notice');
        box.textContent = message;
        box.className = 'notice ' + kind;
        box.hidden = !message;
        const inside = $('#dialog-notice');
        if (inside) { inside.textContent = message; inside.className = 'notice ' + kind; inside.hidden = !message; }
    }
    function controls() {
        $$('form[data-operation] button[type="submit"], button[data-mutation]').forEach(el => { el.disabled = !available || busy || loading; });
        $('#refresh').disabled = busy || loading;
        $$('form[data-operation] input, form[data-operation] select').forEach(el => { el.disabled = busy || !available; });
    }
    async function request(options = {}) {
        let response;
        try { response = await fetch(api, { credentials: 'same-origin', cache: 'no-store', ...options }); }
        catch { throw new Error('Không thể kết nối máy chủ. Hãy kiểm tra kết nối và thử lại.'); }
        let result;
        try { result = await response.json(); }
        catch { throw new Error('Máy chủ không trả về dữ liệu hợp lệ. Vui lòng thử lại sau.'); }
        if (!response.ok || result.ok !== true) throw new Error(typeof result.message === 'string' ? result.message : 'Không thể xử lý yêu cầu. Vui lòng thử lại.');
        return result;
    }
    async function load(announce = true) {
        if (loading) return false;
        loading = true; controls();
        if (announce) notify('Đang tải dữ liệu…');
        try {
            const result = await request();
            const incoming = result.data;
            const lists = ['customers','pitches','pitchTypes','services','staff','bookings','serviceUsage','invoices','teams','tournaments','participation','matches'];
            if (!incoming || !incoming.stats || !lists.every(key => Array.isArray(incoming[key])) || typeof incoming.csrf !== 'string') throw new Error('Dữ liệu máy chủ chưa đầy đủ. Vui lòng làm mới trang.');
            data = incoming; csrf = data.csrf; rules = data.rules || rules; available = true;
            render(); populate();
            if (announce) notify('');
            return true;
        } catch (error) {
            available = false;
            notify(error.message + (data ? ' Dữ liệu đang hiển thị là lần tải trước; chưa thể thực hiện thay đổi.' : ''), 'error');
            if (!data) $$('[id$="-table"], #today-bookings, #pitch-status-container').forEach(el => el.replaceChildren(node('p', 'Không tải được dữ liệu. Chọn “Làm mới” để thử lại.', 'empty')));
            return false;
        } finally { loading = false; controls(); }
    }
    async function mutate(operation, payload, form = null, confirmText = '') {
        if (busy || loading || !available) return;
        if (confirmText && !window.confirm(confirmText)) return;
        busy = true; controls();
        let saved = false;
        try {
            const body = payload instanceof FormData ? payload : new FormData();
            if (!(payload instanceof FormData)) Object.entries(payload).forEach(([key, value]) => body.set(key, String(value)));
            body.set('operation', operation); body.set('_csrf', csrf);
            const result = await request({ method: 'POST', body });
            saved = true;
            if (form) form.reset();
            const updated = await load(false);
            if (updated) {
                if (operation === 'make_invoice') { switchTab('invoices'); showDetail('invoice', result.id); }
                else if (opened) showDetail(opened.kind, opened.id);
                notify(result.message || 'Đã lưu thành công.', 'success');
            } else notify('Đã lưu thay đổi nhưng chưa tải lại được dữ liệu. Vui lòng làm mới trước thao tác tiếp theo.', 'error');
        } catch (error) {
            notify(error.message + (saved ? ' Không gửi lại thao tác; hãy làm mới để kiểm tra.' : ''), 'error');
        } finally { busy = false; controls(); }
    }
    function switchTab(id) {
        if (!$('.tab-content#' + id)) return;
        $$('.tab-content').forEach(el => { el.classList.toggle('active', el.id === id); el.hidden = el.id !== id; });
        $$('.nav-links [data-tab]').forEach(el => {
            el.classList.toggle('active', el.dataset.tab === id);
            if (el.dataset.tab === id) el.setAttribute('aria-current', 'page'); else el.removeAttribute('aria-current');
        });
        history.replaceState(null, '', '#' + id);
    }
    const editable = b => !b.MaHD && b.TrangThai !== rules.booking_cancelled && b.TrangThai !== rules.booking_completed;
    const bookingName = b => `${code('DS', b.MaDatSan)} · ${b.TenKhach} · ${b.TenSan} · ${date(b.ThoiGianBatDau)}`;
    function populate() {
        const sources = {
            customers: [data.customers, 'MaKH', r => `${code('KH', r.MaKH)} · ${r.HoTen} (${r.SoDienThoai})`],
            pitches: [data.pitches.filter(p => p.TinhTrang === rules.pitch_active), 'MaSan', r => `${r.TenSan} · ${r.TenLoai} · ${money(r.GiaThue)}/giờ`],
            staff: [data.staff, 'MaNV', r => `${code('NV', r.MaNV)} · ${r.HoTen}`],
            pitchTypes: [data.pitchTypes, 'MaLoai', r => `${r.TenLoai} (${r.SoNguoi} người)`],
            services: [data.services, 'MaDV', r => `${r.TenDV} · ${money(r.DonGia)}/${r.DonViTinh}`],
            editableBookings: [data.bookings.filter(editable), 'MaDatSan', bookingName],
            tournaments: [data.tournaments, 'MaGiai', r => r.TenGiai]
        };
        $$('select[data-source]').forEach(select => {
            const previous = select.value;
            const [rows, key, label] = sources[select.dataset.source];
            select.replaceChildren(new Option(select.required ? (rows.length ? '-- Chọn --' : 'Chưa có dữ liệu để chọn') : '-- Không tham gia giải --', ''));
            rows.forEach(row => select.add(new Option(label(row), String(row[key]))));
            if (rows.some(row => String(row[key]) === previous)) select.value = previous;
        });
        const old = new Map($$('[data-service-quantity]').map(el => [el.dataset.serviceQuantity, el.value]));
        const services = $('#booking-services'); services.replaceChildren();
        data.services.forEach(s => {
            const label = node('label', null, 'service-option');
            label.append(node('span', `${s.TenDV} (${money(s.DonGia)}/${s.DonViTinh})`));
            const input = node('input'); input.type = 'number'; input.min = '0'; input.max = '2147483647'; input.step = '1'; input.value = old.get(String(s.MaDV)) || '0'; input.dataset.serviceQuantity = s.MaDV; input.setAttribute('aria-label', 'Số lượng ' + s.TenDV);
            label.append(input); services.append(label);
        });
        if (!data.services.length) services.append(node('p', 'Chưa có dịch vụ đi kèm.', 'muted'));
        estimate();
    }
    function estimate() {
        if (!data) return;
        const form = $('#booking-form');
        const pitch = data.pitches.find(p => String(p.MaSan) === form.elements.MaSan.value);
        const duration = minute(form.elements.ThoiGianKetThuc.value) - minute(form.elements.ThoiGianBatDau.value);
        let serviceTotal = 0;
        $$('[data-service-quantity]').forEach(input => {
            const service = data.services.find(s => String(s.MaDV) === input.dataset.serviceQuantity);
            serviceTotal += Number(service.DonGia) * Math.max(0, Number(input.value) || 0);
        });
        $('#estimated-total').textContent = pitch && duration > 0 ? money(Number(pitch.GiaThue) * duration / 60 + serviceTotal) : '—';
        form.elements.ThoiGianKetThuc.setCustomValidity(form.elements.ThoiGianKetThuc.value && form.elements.ThoiGianBatDau.value && !(duration > 0) ? 'Ngày giờ kết thúc phải sau bắt đầu.' : '');
    }
    function action(text, handler, mutation = false, cls = '') {
        const button = node('button', text, 'btn-sm ' + cls); button.type = 'button';
        if (mutation) { button.dataset.mutation = ''; button.disabled = busy || loading || !available; }
        button.addEventListener('click', handler); return button;
    }
    function filtered(rows) {
        const q = $('#search').value.trim().toLocaleLowerCase('vi');
        return q ? rows.filter(row => {
            const values = Object.values(row).map(v => String(v ?? ''));
            const prefixes = { MaDatSan: 'DS', MaHD: 'HD', MaKH: 'KH', MaSan: 'SAN', MaDV: 'DV', MaDoi: 'DB', MaGiai: 'GD', MaNV: 'NV' };
            Object.entries(prefixes).forEach(([key, prefix]) => { if (row[key]) values.push(code(prefix, row[key])); });
            if (row.TrangThai) values.push(bookingState(row.TrangThai).textContent);
            if (row.StatusCode) values.push(pitchState(row.StatusCode).textContent);
            if (row.TrangThaiThanhToan) values.push(invoiceState(row.TrangThaiThanhToan).textContent);
            return values.some(v => v.toLocaleLowerCase('vi').includes(q));
        }) : rows;
    }
    function table(target, headers, rows, cells, empty = 'Chưa có dữ liệu.') {
        const container = typeof target === 'string' ? $('#' + target) : target;
        container.replaceChildren();
        const list = filtered(rows);
        if (!list.length) { container.append(node('p', rows.length ? 'Không tìm thấy kết quả phù hợp.' : empty, 'empty')); return; }
        const wrap = node('div', null, 'table-responsive');
        const t = node('table', null, 'data-table'); const thead = node('thead'); const tr = node('tr');
        headers.forEach(h => tr.append(node('th', h))); thead.append(tr); t.append(thead);
        const tbody = node('tbody');
        list.forEach(row => {
            const line = node('tr'); cells(row).forEach(value => { const td = node('td'); td.append(value instanceof Node ? value : document.createTextNode(String(value ?? '—'))); line.append(td); }); tbody.append(line);
        });
        t.append(tbody); wrap.append(t); container.append(wrap);
    }
    function bookingActions(b) {
        const wrap = node('div', null, 'actions');
        wrap.append(action('Chi tiết', () => showDetail('booking', b.MaDatSan)));
        if (editable(b)) {
            const now = venueNow();
            const pitch = data.pitches.find(p => String(p.MaSan) === String(b.MaSan));
            if (b.TrangThai === rules.booking_new && now >= b.ThoiGianBatDau && now < b.ThoiGianKetThuc && pitch?.TinhTrang === rules.pitch_active) wrap.append(action('Nhận sân', () => mutate('receive_booking', { MaDatSan: b.MaDatSan }), true));
            wrap.append(action('Lập hóa đơn', () => selectBooking('invoices', b.MaDatSan)));
            wrap.append(action('Hủy', () => mutate('cancel_booking', { MaDatSan: b.MaDatSan }, null, 'Hủy lượt đặt ' + code('DS', b.MaDatSan) + '?'), true, 'danger'));
        }
        return wrap;
    }
    function selectBooking(tab, id) {
        $('#detail-dialog').close(); opened = null; switchTab(tab);
        const select = $(`#${tab} select[name="MaDatSan"]`);
        if (select) { select.value = String(id); select.focus(); }
    }
    function render() {
        $('#today-label').textContent = 'Hôm nay, ' + date(data.today) + ' · Giờ Việt Nam';
        $('#stat-revenue').textContent = money(data.stats.revenueToday);
        $('#stat-revenue').title = 'Hóa đơn đã thanh toán có ngày lập hôm nay';
        $('#stat-bookings').textContent = data.stats.bookingsToday + ' đơn';
        $('#stat-pitches').textContent = data.stats.freePitches + ' / ' + data.stats.totalPitches + ' sân';
        $('#stat-customers').textContent = data.stats.customers + ' người';
        const pitches = $('#pitch-status-container'); pitches.replaceChildren();
        filtered(data.pitches).forEach(p => {
            const status = ['free','busy','maintenance'].includes(p.StatusCode) ? p.StatusCode : 'maintenance';
            const box = node('div', null, 'pitch-box is-' + status); box.append(node('strong', p.TenSan), node('span', p.TenLoai, 'muted'), node('span', money(p.GiaThue) + '/giờ', 'muted'), pitchState(p.StatusCode));
            if (status !== 'maintenance') box.append(action('Đặt sân', () => { switchTab('dashboard'); $('#booking-form select[name="MaSan"]').value = String(p.MaSan); estimate(); $('#booking-form').scrollIntoView({ block: 'start' }); }));
            pitches.append(box);
        });
        if (!pitches.childElementCount) pitches.append(node('p', data.pitches.length ? 'Không tìm thấy sân phù hợp.' : 'Chưa có sân bóng.', 'empty'));
        const today = data.bookings.filter(b => b.ThoiGianBatDau < data.today + ' 23:59:59' && b.ThoiGianKetThuc > data.today + ' 00:00:00');
        table('today-bookings', ['Mã đặt','Khách hàng','Sân bóng','Khung giờ','Trạng thái'], today, b => [code('DS', b.MaDatSan), b.TenKhach, b.TenSan, date(b.ThoiGianBatDau) + ' – ' + date(b.ThoiGianKetThuc), bookingState(b.TrangThai)], 'Chưa có lịch đặt trong ngày.');
        table('bookings-table', ['Mã đặt','Khách hàng','Sân / nhân viên','Bắt đầu','Kết thúc','Tạm tính','Trạng thái','Thao tác'], data.bookings, b => [code('DS', b.MaDatSan), b.TenKhach, b.TenSan + ' / ' + b.TenNhanVien, date(b.ThoiGianBatDau), date(b.ThoiGianKetThuc), money(b.TongTien), bookingState(b.TrangThai), bookingActions(b)]);
        table('pitches-table', ['Mã sân','Tên sân','Loại sân','Giá thuê / giờ','Trạng thái'], data.pitches, p => [code('SAN', p.MaSan), p.TenSan, p.TenLoai, money(p.GiaThue), pitchState(p.StatusCode)]);
        table('customers-table', ['Mã KH','Họ tên','Số điện thoại','Địa chỉ'], data.customers, c => [code('KH', c.MaKH), c.HoTen, c.SoDienThoai, c.DiaChi || '—']);
        table('services-table', ['Mã DV','Dịch vụ','Đơn vị','Đơn giá'], data.services, s => [code('DV', s.MaDV), s.TenDV, s.DonViTinh, money(s.DonGia)]);
        table('invoices-table', ['Hóa đơn','Khách / sân','Nhân viên','Ngày lập','Tổng tiền','Thanh toán','Thao tác'], data.invoices, i => {
            const actions = node('div', null, 'actions'); actions.append(action('Chi tiết', () => showDetail('invoice', i.MaHD)));
            if (i.TrangThaiThanhToan === rules.invoice_unpaid) actions.append(action('Thanh toán', () => pay(i), true));
            return [code('HD', i.MaHD), i.TenKhach + ' / ' + i.TenSan, i.TenNhanVien, date(i.NgayLap), money(i.TongTien), invoiceState(i.TrangThaiThanhToan), actions];
        });
        table('tournaments-table', ['Mã giải','Tên giải','Bắt đầu','Kết thúc','Số đội'], data.tournaments, g => [code('GD', g.MaGiai), g.TenGiai, date(g.NgayBatDau), date(g.NgayKetThuc), data.participation.filter(p => String(p.MaGiai) === String(g.MaGiai)).length]);
        table('teams-table', ['Mã đội','Tên đội','Đội trưởng','Điện thoại','Giải tham gia'], data.teams, t => [code('DB', t.MaDoi), t.TenDoi, t.TenDoiTruong, t.SoDienThoai, data.participation.filter(p => String(p.MaDoi) === String(t.MaDoi)).map(p => data.tournaments.find(g => String(g.MaGiai) === String(p.MaGiai))?.TenGiai || code('GD', p.MaGiai)).join(', ') || 'Chưa tham gia giải']);
        table('matches-table', ['Giải đấu','Trận đấu','Sân','Thời gian','Tỷ số'], data.matches, m => [m.TenGiai, m.TenDoiNha + ' – ' + m.TenDoiKhach, m.TenSan, date(m.ThoiGianThiDau), m.TySoNha !== null && m.TySoKhach !== null ? m.TySoNha + ' – ' + m.TySoKhach : 'Chưa có kết quả'], 'Chưa có lịch thi đấu được xếp.');
        controls();
    }
    function pay(invoice) {
        return mutate('pay_invoice', { MaHD: invoice.MaHD }, null, 'Đã thu đủ ' + money(invoice.TongTien) + ' cho hóa đơn ' + code('HD', invoice.MaHD) + '? Xác nhận để ghi nhận đã thanh toán.');
    }
    function showDetail(kind, id) {
        if (!data) return;
        const invoice = kind === 'invoice' ? data.invoices.find(i => String(i.MaHD) === String(id)) : null;
        const booking = data.bookings.find(b => String(b.MaDatSan) === String(invoice ? invoice.MaDatSan : id));
        if (!booking) return;
        opened = { kind, id };
        $('#dialog-title').textContent = invoice ? 'Hóa đơn ' + code('HD', invoice.MaHD) : 'Lượt đặt ' + code('DS', booking.MaDatSan);
        const content = $('#dialog-content'); content.replaceChildren();
        const notice = node('div', '', 'notice'); notice.id = 'dialog-notice'; notice.hidden = true; notice.setAttribute('role', 'status'); content.append(notice);
        const info = node('div', null, 'detail-grid');
        const items = [['Khách hàng', booking.TenKhach], ['Sân bóng', booking.TenSan], ['Bắt đầu', date(booking.ThoiGianBatDau)], ['Kết thúc', date(booking.ThoiGianKetThuc)], ['Nhân viên', invoice ? invoice.TenNhanVien : booking.TenNhanVien], ['Lượt đặt', code('DS', booking.MaDatSan)]];
        if (invoice) items.push(['Ngày lập', date(invoice.NgayLap)]);
        items.forEach(([label, value]) => { const p = node('p'); p.append(node('strong', label + ': '), document.createTextNode(String(value))); info.append(p); });
        content.append(info, invoice ? invoiceState(invoice.TrangThaiThanhToan) : bookingState(booking.TrangThai));
        const minutes = minute(booking.ThoiGianKetThuc) - minute(booking.ThoiGianBatDau);
        const usage = data.serviceUsage.filter(s => String(s.MaDatSan) === String(booking.MaDatSan));
        const lines = [{ name: 'Tiền sân · ' + booking.TenSan, quantity: minutes + ' phút', price: money(booking.GiaThueLucDat) + '/giờ', total: money(minutes / 60 * Number(booking.GiaThueLucDat)) }, ...usage.map(s => ({ name: s.TenDV, quantity: s.SoLuong + ' ' + s.DonViTinh, price: money(s.DonGiaLucDung), total: money(Number(s.SoLuong) * Number(s.DonGiaLucDung)) }))];
        const detail = node('div'); content.append(node('h3', 'Chi tiết tiền sân & dịch vụ'), detail);
        // Detail always shows every billed item, independent of the page search filter.
        const search = $('#search').value; $('#search').value = '';
        table(detail, ['Nội dung','Số lượng','Đơn giá','Thành tiền'], lines, r => [r.name,r.quantity,r.price,r.total]); $('#search').value = search;
        content.append(node('p', (invoice ? 'Tổng thanh toán: ' : 'Tạm tính: ') + money(invoice ? invoice.TongTien : booking.TongTien), 'detail-total'));
        if (invoice && invoice.TrangThaiThanhToan === rules.invoice_unpaid) { const wrap = node('div', null, 'invoice-pay'); wrap.append(action('Xác nhận đã thu tiền', () => pay(invoice), true)); content.append(wrap); }
        if (!invoice && booking.MaHD) content.append(action('Xem hóa đơn', () => showDetail('invoice', booking.MaHD)));
        else if (!invoice && editable(booking)) { const wrap = node('div', null, 'actions'); wrap.append(action('Thêm dịch vụ', () => selectBooking('services', booking.MaDatSan)), action('Lập hóa đơn', () => selectBooking('invoices', booking.MaDatSan))); content.append(wrap); }
        const dialog = $('#detail-dialog'); if (!dialog.open) dialog.showModal();
        controls();
    }
    $$('[data-tab]').forEach(button => button.addEventListener('click', () => switchTab(button.dataset.tab)));
    $('#search').addEventListener('input', () => { if (data) render(); });
    $('#refresh').addEventListener('click', () => load());
    $('#close-dialog').addEventListener('click', () => { $('#detail-dialog').close(); opened = null; });
    $('#detail-dialog').addEventListener('close', () => { opened = null; });
    $('#booking-form').addEventListener('input', estimate);
    $('#booking-form').addEventListener('change', estimate);
    $$('form[data-operation]').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        if (busy || loading || !available || !form.reportValidity()) return;
        const payload = new FormData(form);
        if (form.dataset.operation === 'add_booking') {
            const services = $$('[data-service-quantity]').filter(el => Number(el.value) > 0).map(el => ({ MaDV: Number(el.dataset.serviceQuantity), SoLuong: Number(el.value) }));
            payload.set('Services', JSON.stringify(services));
        }
        if (form.dataset.operation === 'add_tournament' && payload.get('NgayKetThuc') < payload.get('NgayBatDau')) { notify('Ngày kết thúc giải không được trước ngày bắt đầu.', 'error'); return; }
        mutate(form.dataset.operation, payload, form);
    }));
    const initialTab = location.hash.slice(1);
    if (['dashboard','booking','pitches','customers','services','invoices','tournaments'].includes(initialTab)) switchTab(initialTab);
    load();
})();
