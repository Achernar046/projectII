/**
 * assets/js/app.js
 * Northwind BMS — Application Controller for all 12 Screens
 */

const App = {
  currentScreen: 'login',
  charts: {},
  cachedCategories: [],
  cachedSuppliers: [],
  cachedShippers: [],
  cachedEmployees: [],

  init() {
    this.bindEvents();
    this.checkAuth();
    this.setupHashRouting();
  },

  setupHashRouting() {
    const handleHash = () => {
      const hash = window.location.hash.replace('#', '') || (API.isAuthenticated() ? 'dashboard' : 'login');
      this.showScreen(hash);
    };
    window.addEventListener('hashchange', handleHash);
    handleHash();
  },

  checkAuth() {
    const user = API.getUser();
    if (user && API.isAuthenticated()) {
      this.updateUserUI(user);
    } else {
      this.showScreen('login');
    }
  },

  updateUserUI(user) {
    const nameEl = document.getElementById('topbar-username');
    const roleEl = document.getElementById('topbar-userrole');
    const avatarEl = document.getElementById('user-avatar');
    if (nameEl) nameEl.textContent = user.full_name || user.username;
    if (roleEl) roleEl.textContent = user.role ? (user.role.toUpperCase()) : 'STAFF';
    if (avatarEl) avatarEl.textContent = (user.username || 'A').substring(0, 2).toUpperCase();
  },

  showScreen(screenId) {
    // If not authenticated and trying to access protected screen
    if (!API.isAuthenticated() && screenId !== 'login') {
      this.toast('กรุณาเข้าสู่ระบบก่อนใช้งาน', 'warning');
      window.location.hash = 'login';
      return;
    }

    // Hide all screens
    document.querySelectorAll('.screen-view').forEach(el => el.classList.remove('active'));

    // If login screen, hide main app shell
    const shellEl = document.getElementById('app-shell-container');
    const loginEl = document.getElementById('screen-login');

    if (screenId === 'login') {
      if (shellEl) shellEl.style.display = 'none';
      if (loginEl) loginEl.classList.add('active');
      this.currentScreen = 'login';
      return;
    } else {
      if (shellEl) shellEl.style.display = 'flex';
    }

    // Show target screen
    const target = document.getElementById(`screen-${screenId}`);
    if (target) {
      target.classList.add('active');
      this.currentScreen = screenId;
    }

    // Update active nav links
    document.querySelectorAll('.sidebar-link').forEach(link => {
      if (link.getAttribute('href') === `#${screenId}`) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });

    // Close mobile sidebar if open
    const sidebar = document.getElementById('app-sidebar');
    if (sidebar) sidebar.classList.remove('show');

    // Trigger Screen Data Loading
    this.loadScreenData(screenId);
  },

  loadScreenData(screenId) {
    switch (screenId) {
      case 'dashboard':
        this.loadDashboard();
        break;
      case 'products':
        this.loadProducts();
        break;
      case 'product-form':
        this.initProductForm();
        break;
      case 'categories':
        this.loadCategories();
        break;
      case 'orders':
        this.loadOrders();
        break;
      case 'customers':
        this.loadCustomers();
        break;
      case 'suppliers':
        this.loadSuppliers();
        break;
      case 'employees':
        this.loadEmployees();
        break;
      case 'shippers':
        this.loadShippers();
        break;
      case 'reports':
        this.loadReports();
        break;
    }
  },

  bindEvents() {
    // Toggle Mobile Sidebar
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        document.getElementById('app-sidebar').classList.toggle('show');
      });
    }

    // Login Form Submit
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', (e) => this.handleLogin(e));
    }

    // Logout Button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => this.handleLogout());
    }

    // Product Search & Filter
    const prodSearch = document.getElementById('prod-search-input');
    if (prodSearch) {
      prodSearch.addEventListener('input', this.debounce(() => this.loadProducts(1), 350));
    }
    const catFilter = document.getElementById('prod-category-filter');
    if (catFilter) {
      catFilter.addEventListener('change', () => this.loadProducts(1));
    }

    // Product Form Submit
    const prodForm = document.getElementById('product-form-element');
    if (prodForm) {
      prodForm.addEventListener('submit', (e) => this.handleProductSave(e));
    }
  },

  // ═══════════════════════════════════════════════════════
  // 1. AUTHENTICATION
  // ═══════════════════════════════════════════════════════
  async handleLogin(e) {
    e.preventDefault();
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    const btn = document.getElementById('login-submit-btn');

    if (!username || !password) {
      this.toast('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 'warning');
      return;
    }

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังเข้าสู่ระบบ...';

      const res = await API.post('/auth/login', { username, password });

      API.setToken(res.data.token);
      API.setUser(res.data.user);
      this.updateUserUI(res.data.user);

      this.toast('เข้าสู่ระบบสำเร็จ ยินดีต้อนรับคุณ ' + res.data.user.full_name, 'success');
      window.location.hash = 'dashboard';

    } catch (err) {
      this.toast(err.message || 'เข้าสู่ระบบไม่สำเร็จ กรุณาตรวจสอบข้อมูล', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ';
    }
  },

  async handleLogout() {
    try {
      await API.post('/auth/logout');
    } catch (e) {
      // ignore
    }
    API.setToken(null);
    API.setUser(null);
    this.toast('ออกจากระบบเรียบร้อยแล้ว', 'info');
    window.location.hash = 'login';
  },

  // ═══════════════════════════════════════════════════════
  // 2. DASHBOARD
  // ═══════════════════════════════════════════════════════
  async loadDashboard() {
    try {
      const [kpisRes, chartsRes] = await Promise.all([
        API.get('/dashboard/kpis'),
        API.get('/dashboard/charts')
      ]);

      const kpi = kpisRes.data;
      document.getElementById('kpi-revenue').textContent = '฿' + Number(kpi.total_revenue).toLocaleString('th-TH', { minimumFractionDigits: 2 });
      document.getElementById('kpi-orders').textContent = Number(kpi.total_orders).toLocaleString('th-TH');
      document.getElementById('kpi-products').textContent = Number(kpi.total_products).toLocaleString('th-TH');
      document.getElementById('kpi-customers').textContent = Number(kpi.total_customers).toLocaleString('th-TH');

      this.renderSalesChart(chartsRes.data.monthly_trend);
      this.renderCategoryChart(chartsRes.data.category_sales);
      this.renderTopProductsTable(chartsRes.data.top_products);
      this.renderTopCustomersTable(chartsRes.data.top_customers);

    } catch (err) {
      this.toast('เกิดข้อผิดพลาดในการโหลดข้อมูลแดชบอร์ด: ' + err.message, 'danger');
    }
  },

  renderSalesChart(monthlyData) {
    const ctx = document.getElementById('dashboardSalesChart');
    if (!ctx) return;

    if (this.charts.sales) {
      this.charts.sales.destroy();
    }

    const labels = monthlyData.map(d => d.month_name);
    const revenues = monthlyData.map(d => d.revenue);

    this.charts.sales = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'ยอดขาย (บาท)',
          data: revenues,
          backgroundColor: '#3b82f6',
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (val) => '฿' + val.toLocaleString()
            }
          }
        }
      }
    });
  },

  renderCategoryChart(catData) {
    const ctx = document.getElementById('dashboardCategoryChart');
    if (!ctx) return;

    if (this.charts.category) {
      this.charts.category.destroy();
    }

    const labels = catData.map(c => c.c_CategoryName);
    const data = catData.map(c => c.total_revenue);

    this.charts.category = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: [
            '#2563eb', '#38bdf8', '#34d399', '#f59e0b',
            '#ec4899', '#8b5cf6', '#a855f7', '#06b6d4', '#64748b'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  },

  renderTopProductsTable(products) {
    const tbody = document.getElementById('top-products-tbody');
    if (!tbody) return;

    tbody.innerHTML = products.map((p, idx) => `
      <tr>
        <td class="fw-bold text-muted">${idx + 1}</td>
        <td class="fw-semibold">${this.escape(p.c_ProductName)}</td>
        <td><span class="badge badge-soft-primary">${this.escape(p.c_CategoryName || '-')}</span></td>
        <td class="text-end fw-bold text-primary">${Number(p.total_sold).toLocaleString()} หน่วย</td>
        <td class="text-end fw-bold">฿${Number(p.total_sales).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
      </tr>
    `).join('');
  },

  renderTopCustomersTable(customers) {
    const tbody = document.getElementById('top-customers-tbody');
    if (!tbody) return;

    tbody.innerHTML = customers.map((c, idx) => `
      <tr>
        <td class="fw-bold">${idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : idx + 1}</td>
        <td class="fw-semibold">${this.escape(c.c_customername)}</td>
        <td><span class="badge badge-soft-secondary">${this.escape(c.c_country || '-')}</span></td>
        <td class="text-end fw-bold">${c.order_count} ออร์เดอร์</td>
      </tr>
    `).join('');
  },

  // ═══════════════════════════════════════════════════════
  // 3. PRODUCTS LIST
  // ═══════════════════════════════════════════════════════
  async loadProducts(page = 1) {
    try {
      const search = document.getElementById('prod-search-input')?.value.trim() || '';
      const catId = document.getElementById('prod-category-filter')?.value || '';

      const res = await API.get('/products', {
        page,
        limit: 10,
        search,
        category_id: catId
      });

      this.renderProductsTable(res.data);
      this.renderPagination('prod-pagination', res.meta, (p) => this.loadProducts(p));

      // Populate category filter dropdown if empty
      this.populateCategoryFilter();

    } catch (err) {
      this.toast('โหลดรายการสินค้าไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async populateCategoryFilter() {
    const filter = document.getElementById('prod-category-filter');
    if (!filter || filter.children.length > 1) return;

    const res = await API.get('/categories');
    this.cachedCategories = res.data;
    res.data.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.i_CategoryID;
      opt.textContent = `${c.c_CategoryName} (${c.product_count})`;
      filter.appendChild(opt);
    });
  },

  renderProductsTable(products) {
    const tbody = document.getElementById('products-tbody');
    if (!tbody) return;

    if (products.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">ไม่พบข้อมูลสินค้าตรงกับเงื่อนไข</td></tr>`;
      return;
    }

    tbody.innerHTML = products.map(p => `
      <tr>
        <td class="fw-bold text-muted">#${p.i_ProductID}</td>
        <td>
          <div class="fw-semibold text-dark">${this.escape(p.c_ProductName)}</div>
          <small class="text-muted">หน่วย: ${this.escape(p.c_Unit)}</small>
        </td>
        <td><span class="badge badge-soft-primary">${this.escape(p.c_CategoryName || '-')}</span></td>
        <td class="text-muted small">${this.escape(p.c_SupplierName || '-')}</td>
        <td class="text-end fw-bold text-primary">฿${Number(p.i_Price).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
        <td class="text-center">
          <span class="badge badge-soft-success">พร้อมจำหน่าย</span>
        </td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="App.editProduct(${p.i_ProductID})">
            <i class="bi bi-pencil"></i> แก้ไข
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="App.deleteProduct(${p.i_ProductID}, '${this.escape(p.c_ProductName)}')">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  },

  // ═══════════════════════════════════════════════════════
  // 4. ADD & EDIT PRODUCT FORM
  // ═══════════════════════════════════════════════════════
  async initProductForm(productId = null) {
    const form = document.getElementById('product-form-element');
    if (!form) return;

    form.reset();
    form.classList.remove('was-validated');

    // Populate dropdowns for categories and suppliers
    await Promise.all([
      this.populateFormSelect('form-prod-category', '/categories', 'i_CategoryID', 'c_CategoryName'),
      this.populateFormSelect('form-prod-supplier', '/suppliers', 'i_SupplierID', 'c_SupplierName')
    ]);

    const titleEl = document.getElementById('product-form-title');
    const idInput = document.getElementById('form-prod-id');

    if (productId) {
      if (titleEl) titleEl.textContent = `แก้ไขข้อมูลสินค้า #${productId}`;
      idInput.value = productId;

      try {
        const res = await API.get(`/products/${productId}`);
        const p = res.data;
        document.getElementById('form-prod-name').value = p.c_ProductName;
        document.getElementById('form-prod-price').value = p.i_Price;
        document.getElementById('form-prod-unit').value = p.c_Unit;
        document.getElementById('form-prod-category').value = p.i_CategoryID;
        document.getElementById('form-prod-supplier').value = p.i_SupplierID;
      } catch (err) {
        this.toast('ไม่สามารถดึงข้อมูลสินค้าได้: ' + err.message, 'danger');
        window.location.hash = 'products';
      }
    } else {
      if (titleEl) titleEl.textContent = 'เพิ่มสินค้าใหม่';
      idInput.value = '';
    }
  },

  async populateFormSelect(selectId, endpoint, valKey, textKey) {
    const select = document.getElementById(selectId);
    if (!select) return;

    select.innerHTML = '<option value="">-- กรุณาเลือก --</option>';
    try {
      const res = await API.get(endpoint, { limit: 100 });
      const items = res.data || [];
      items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item[valKey];
        opt.textContent = item[textKey];
        select.appendChild(opt);
      });
    } catch (e) {
      console.error(e);
    }
  },

  editProduct(id) {
    window.location.hash = 'product-form';
    this.initProductForm(id);
  },

  async handleProductSave(e) {
    e.preventDefault();
    const form = document.getElementById('product-form-element');

    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      this.toast('กรุณากรอกข้อมูลในฟอร์มให้ครบถ้วนถูกต้อง', 'warning');
      return;
    }

    const id = document.getElementById('form-prod-id').value;
    const body = {
      c_ProductName: document.getElementById('form-prod-name').value.trim(),
      i_Price: parseFloat(document.getElementById('form-prod-price').value),
      c_Unit: document.getElementById('form-prod-unit').value.trim(),
      i_CategoryID: parseInt(document.getElementById('form-prod-category').value),
      i_SupplierID: parseInt(document.getElementById('form-prod-supplier').value)
    };

    const submitBtn = document.getElementById('form-prod-submit-btn');

    try {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึก...';

      if (id) {
        await API.put(`/products/${id}`, body);
        this.toast('อัปเดตข้อมูลสินค้าเรียบร้อยแล้ว', 'success');
      } else {
        await API.post('/products', body);
        this.toast('เพิ่มสินค้าใหม่เรียบร้อยแล้ว', 'success');
      }

      window.location.hash = 'products';

    } catch (err) {
      this.toast('เกิดข้อผิดพลาดในการบันทึก: ' + err.message, 'danger');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> บันทึกข้อมูล';
    }
  },

  async deleteProduct(id, name) {
    if (!confirm(`คุณต้องการลบสินค้า "${name}" (ID #${id}) ใช่หรือไม่?`)) return;

    try {
      await API.delete(`/products/${id}`);
      this.toast('ลบสินค้าเรียบร้อยแล้ว', 'success');
      this.loadProducts(1);
    } catch (err) {
      this.toast('ลบสินค้าไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 5. CATEGORIES
  // ═══════════════════════════════════════════════════════
  async loadCategories() {
    try {
      const res = await API.get('/categories');
      const tbody = document.getElementById('categories-tbody');
      if (!tbody) return;

      tbody.innerHTML = res.data.map(c => `
        <tr>
          <td class="fw-bold text-muted">#${c.i_CategoryID}</td>
          <td class="fw-semibold text-primary">${this.escape(c.c_CategoryName)}</td>
          <td class="text-muted">${this.escape(c.c_Description)}</td>
          <td><span class="badge badge-soft-primary">${c.product_count} รายการ</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1" onclick="App.openCategoryModal(${c.i_CategoryID}, '${this.escape(c.c_CategoryName)}', '${this.escape(c.c_Description)}')">
              <i class="bi bi-pencil"></i> แก้ไข
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="App.deleteCategory(${c.i_CategoryID}, '${this.escape(c.c_CategoryName)}')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      this.toast('โหลดหมวดหมู่ไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  openCategoryModal(id = null, name = '', desc = '') {
    document.getElementById('cat-modal-id').value = id || '';
    document.getElementById('cat-modal-name').value = name;
    document.getElementById('cat-modal-desc').value = desc;
    document.getElementById('cat-modal-title').textContent = id ? `แก้ไขหมวดหมู่ #${id}` : 'เพิ่มหมวดหมู่สินค้า';

    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
  },

  async saveCategoryModal() {
    const id = document.getElementById('cat-modal-id').value;
    const name = document.getElementById('cat-modal-name').value.trim();
    const desc = document.getElementById('cat-modal-desc').value.trim();

    if (!name || !desc) {
      this.toast('กรุณากรอกชื่อหมวดหมู่และคำอธิบาย', 'warning');
      return;
    }

    try {
      if (id) {
        await API.put(`/categories/${id}`, { c_CategoryName: name, c_Description: desc });
        this.toast('แก้ไขหมวดหมู่เรียบร้อยแล้ว', 'success');
      } else {
        await API.post('/categories', { c_CategoryName: name, c_Description: desc });
        this.toast('เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว', 'success');
      }

      bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
      this.loadCategories();
    } catch (err) {
      this.toast('บันทึกไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async deleteCategory(id, name) {
    if (!confirm(`ต้องการลบหมวดหมู่ "${name}" ใช่หรือไม่?`)) return;
    try {
      await API.delete(`/categories/${id}`);
      this.toast('ลบหมวดหมู่เรียบร้อยแล้ว', 'success');
      this.loadCategories();
    } catch (err) {
      this.toast('ลบไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 6. ORDERS LIST & STATUS FILTER
  // ═══════════════════════════════════════════════════════
  async loadOrders(page = 1) {
    try {
      const search = document.getElementById('orders-search-input')?.value.trim() || '';
      const shipperId = document.getElementById('orders-shipper-filter')?.value || '';

      const res = await API.get('/orders', {
        page,
        limit: 10,
        search,
        shipper_id: shipperId
      });

      this.renderOrdersTable(res.data);
      this.renderPagination('orders-pagination', res.meta, (p) => this.loadOrders(p));
      this.populateShipperFilter();

    } catch (err) {
      this.toast('โหลดรายการคำสั่งซื้อไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async populateShipperFilter() {
    const select = document.getElementById('orders-shipper-filter');
    if (!select || select.children.length > 1) return;

    const res = await API.get('/shippers');
    res.data.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.i_ShipperID;
      opt.textContent = s.c_ShipperName;
      select.appendChild(opt);
    });
  },

  renderOrdersTable(orders) {
    const tbody = document.getElementById('orders-tbody');
    if (!tbody) return;

    if (orders.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">ไม่พบข้อมูลคำสั่งซื้อ</td></tr>`;
      return;
    }

    tbody.innerHTML = orders.map(o => `
      <tr>
        <td class="fw-bold text-primary">#${o.i_OrderID}</td>
        <td>
          <div class="fw-semibold text-dark">${this.escape(o.c_customername || '-')}</div>
          <small class="text-muted"><i class="bi bi-geo-alt"></i> ${this.escape(o.c_country || '-')}</small>
        </td>
        <td>${this.escape(o.c_OrderDate)}</td>
        <td><span class="badge badge-soft-secondary">${this.escape(o.employee_name || '-')}</span></td>
        <td><span class="badge badge-soft-primary"><i class="bi bi-truck me-1"></i>${this.escape(o.c_ShipperName || '-')}</span></td>
        <td class="text-end fw-bold text-success">฿${Number(o.total_amount).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-primary" onclick="App.viewOrderDetail(${o.i_OrderID})">
            <i class="bi bi-receipt"></i> ดูรายละเอียด
          </button>
        </td>
      </tr>
    `).join('');
  },

  // ═══════════════════════════════════════════════════════
  // 7. ORDER DETAILS & ORDER ITEMS
  // ═══════════════════════════════════════════════════════
  async viewOrderDetail(orderId) {
    window.location.hash = 'order-detail';
    try {
      const res = await API.get(`/orders/${orderId}`);
      const o = res.data;

      document.getElementById('detail-order-id').textContent = `#${o.i_OrderID}`;
      document.getElementById('detail-order-date').textContent = o.c_OrderDate;
      document.getElementById('detail-customer-name').textContent = o.c_customername;
      document.getElementById('detail-contact-name').textContent = o.c_contactname || '-';
      document.getElementById('detail-customer-address').textContent = `${o.c_address || ''} ${o.c_city || ''} ${o.c_country || ''}`;
      document.getElementById('detail-employee-name').textContent = o.employee_name || '-';
      document.getElementById('detail-shipper-name').textContent = o.c_ShipperName || '-';
      document.getElementById('detail-shipper-phone').textContent = o.shipper_phone || '-';
      document.getElementById('detail-total-amount').textContent = '฿' + Number(o.total_amount).toLocaleString('th-TH', { minimumFractionDigits: 2 });

      // Store current viewing order id for item operations
      this.currentOrderId = orderId;

      // Render Items Table
      const tbody = document.getElementById('order-items-tbody');
      tbody.innerHTML = (o.items || []).map((item, idx) => `
        <tr>
          <td>${idx + 1}</td>
          <td>
            <div class="fw-semibold">${this.escape(item.c_ProductName)}</div>
            <small class="text-muted">${this.escape(item.c_Unit || '')}</small>
          </td>
          <td><span class="badge badge-soft-primary">${this.escape(item.c_CategoryName || '-')}</span></td>
          <td class="text-end">฿${Number(item.i_Price).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
          <td class="text-center fw-bold">${item.i_Quantity}</td>
          <td class="text-end fw-bold text-primary">฿${Number(item.line_total).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-danger" onclick="App.deleteOrderItem(${item.i_OrderDetailID})">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `).join('');

    } catch (err) {
      this.toast('ไม่สามารถดึงข้อมูลคำสั่งซื้อได้: ' + err.message, 'danger');
      window.location.hash = 'orders';
    }
  },

  async openAddItemModal() {
    if (!this.currentOrderId) return;
    const select = document.getElementById('modal-item-product');
    if (select) {
      select.innerHTML = '<option value="">-- กรุณาเลือกสินค้า --</option>';
      try {
        const res = await API.get('/products', { limit: 100 });
        (res.data || []).forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.i_ProductID;
          opt.textContent = `${item.c_ProductName} (฿${Number(item.i_Price).toLocaleString('th-TH')})`;
          select.appendChild(opt);
        });
      } catch (e) {
        console.error('Failed to load products for modal:', e);
      }
    }
    document.getElementById('modal-item-qty').value = 1;
    new bootstrap.Modal(document.getElementById('orderItemModal')).show();
  },

  async saveOrderItemModal() {
    const prodId = document.getElementById('modal-item-product').value;
    const qty = parseInt(document.getElementById('modal-item-qty').value);

    if (!prodId || isNaN(qty) || qty < 1) {
      this.toast('กรุณาเลือกสินค้าและระบุจำนวนที่ถูกต้อง', 'warning');
      return;
    }

    try {
      await API.post(`/orders/${this.currentOrderId}/items`, {
        i_ProductID: parseInt(prodId),
        i_Quantity: qty
      });
      this.toast('เพิ่มรายการสินค้าลงในออร์เดอร์สำเร็จ', 'success');
      bootstrap.Modal.getInstance(document.getElementById('orderItemModal')).hide();
      this.viewOrderDetail(this.currentOrderId);
    } catch (err) {
      this.toast('เพิ่มสินค้าไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async deleteOrderItem(detailId) {
    if (!confirm('ต้องการลบรายการสินค้านี้ออกจากคำสั่งซื้อใช่หรือไม่?')) return;
    try {
      await API.delete(`/orders/${this.currentOrderId}/items/${detailId}`);
      this.toast('ลบรายการสินค้าเรียบร้อยแล้ว', 'success');
      this.viewOrderDetail(this.currentOrderId);
    } catch (err) {
      this.toast('ลบรายการไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 8. CUSTOMERS
  // ═══════════════════════════════════════════════════════
  async loadCustomers(page = 1) {
    try {
      const search = document.getElementById('cust-search-input')?.value.trim() || '';
      const res = await API.get('/customers', { page, limit: 10, search });

      const tbody = document.getElementById('customers-tbody');
      tbody.innerHTML = res.data.map(c => `
        <tr>
          <td class="fw-bold text-muted">#${c.i_customerid}</td>
          <td class="fw-semibold">${this.escape(c.c_customername)}</td>
          <td>${this.escape(c.c_contactname)}</td>
          <td><span class="badge badge-soft-secondary">${this.escape(c.c_country)}</span></td>
          <td class="text-muted small">${this.escape(c.c_city || '-')}</td>
          <td><span class="badge badge-soft-primary">${c.order_count} ออร์เดอร์</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1" onclick="App.openCustomerModal(${c.i_customerid})">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="App.deleteCustomer(${c.i_customerid}, '${this.escape(c.c_customername)}')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `).join('');

      this.renderPagination('cust-pagination', res.meta, (p) => this.loadCustomers(p));
    } catch (err) {
      this.toast('โหลดข้อมูลลูกค้าไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async openCustomerModal(id = null) {
    const modalEl = document.getElementById('customerModal');
    const form = document.getElementById('customer-modal-form');
    form.reset();

    document.getElementById('cust-modal-id').value = id || '';
    document.getElementById('cust-modal-title').textContent = id ? `แก้ไขข้อมูลลูกค้า #${id}` : 'เพิ่มลูกค้าใหม่';

    if (id) {
      try {
        const res = await API.get(`/customers/${id}`);
        const c = res.data;
        document.getElementById('cust-modal-name').value = c.c_customername;
        document.getElementById('cust-modal-contact').value = c.c_contactname;
        document.getElementById('cust-modal-address').value = c.c_address || '';
        document.getElementById('cust-modal-city').value = c.c_city || '';
        document.getElementById('cust-modal-postal').value = c.c_postalcode || '';
        document.getElementById('cust-modal-country').value = c.c_country;
      } catch (err) {
        this.toast('ดึงข้อมูลลูกค้าไม่สำเร็จ: ' + err.message, 'danger');
        return;
      }
    }

    new bootstrap.Modal(modalEl).show();
  },

  async saveCustomerModal() {
    const id = document.getElementById('cust-modal-id').value;
    const body = {
      c_customername: document.getElementById('cust-modal-name').value.trim(),
      c_contactname: document.getElementById('cust-modal-contact').value.trim(),
      c_address: document.getElementById('cust-modal-address').value.trim(),
      c_city: document.getElementById('cust-modal-city').value.trim(),
      c_postalcode: document.getElementById('cust-modal-postal').value.trim(),
      c_country: document.getElementById('cust-modal-country').value.trim(),
    };

    if (!body.c_customername || !body.c_contactname || !body.c_country) {
      this.toast('กรุณากรอกชื่อลูกค้า ผู้ติดต่อ และประเทศ', 'warning');
      return;
    }

    try {
      if (id) {
        await API.put(`/customers/${id}`, body);
        this.toast('แก้ไขข้อมูลลูกค้าสำเร็จ', 'success');
      } else {
        await API.post('/customers', body);
        this.toast('เพิ่มลูกค้าใหม่สำเร็จ', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
      this.loadCustomers();
    } catch (err) {
      this.toast('บันทึกไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async deleteCustomer(id, name) {
    if (!confirm(`ต้องการลบลูกค้า "${name}" (#${id}) ใช่หรือไม่?`)) return;
    try {
      await API.delete(`/customers/${id}`);
      this.toast('ลบลูกค้าเรียบร้อยแล้ว', 'success');
      this.loadCustomers();
    } catch (err) {
      this.toast('ลบลูกค้าไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 9. SUPPLIERS
  // ═══════════════════════════════════════════════════════
  async loadSuppliers(page = 1) {
    try {
      const search = document.getElementById('sup-search-input')?.value.trim() || '';
      const res = await API.get('/suppliers', { page, limit: 10, search });

      const tbody = document.getElementById('suppliers-tbody');
      tbody.innerHTML = res.data.map(s => `
        <tr>
          <td class="fw-bold text-muted">#${s.i_SupplierID}</td>
          <td class="fw-semibold">${this.escape(s.c_SupplierName)}</td>
          <td>${this.escape(s.c_ContactName)}</td>
          <td><span class="badge badge-soft-secondary">${this.escape(s.c_Country)}</span></td>
          <td>${this.escape(s.c_Phone)}</td>
          <td><span class="badge badge-soft-primary">${s.product_count} สินค้า</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1" onclick="App.openSupplierModal(${s.i_SupplierID})">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="App.deleteSupplier(${s.i_SupplierID}, '${this.escape(s.c_SupplierName)}')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `).join('');

      this.renderPagination('sup-pagination', res.meta, (p) => this.loadSuppliers(p));
    } catch (err) {
      this.toast('โหลดผู้จัดจำหน่ายไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async openSupplierModal(id = null) {
    const form = document.getElementById('supplier-modal-form');
    form.reset();
    document.getElementById('sup-modal-id').value = id || '';
    document.getElementById('sup-modal-title').textContent = id ? `แก้ไขข้อมูลผู้จัดจำหน่าย #${id}` : 'เพิ่มผู้จัดจำหน่ายใหม่';

    if (id) {
      try {
        const res = await API.get(`/suppliers/${id}`);
        const s = res.data;
        document.getElementById('sup-modal-name').value = s.c_SupplierName;
        document.getElementById('sup-modal-contact').value = s.c_ContactName;
        document.getElementById('sup-modal-address').value = s.c_Address || '';
        document.getElementById('sup-modal-city').value = s.c_City || '';
        document.getElementById('sup-modal-postal').value = s.c_PostalCode || '';
        document.getElementById('sup-modal-country').value = s.c_Country;
        document.getElementById('sup-modal-phone').value = s.c_Phone;
      } catch (err) {
        this.toast('ดึงข้อมูลผู้จัดจำหน่ายไม่สำเร็จ: ' + err.message, 'danger');
        return;
      }
    }

    new bootstrap.Modal(document.getElementById('supplierModal')).show();
  },

  async saveSupplierModal() {
    const id = document.getElementById('sup-modal-id').value;
    const body = {
      c_SupplierName: document.getElementById('sup-modal-name').value.trim(),
      c_ContactName: document.getElementById('sup-modal-contact').value.trim(),
      c_Address: document.getElementById('sup-modal-address').value.trim(),
      c_City: document.getElementById('sup-modal-city').value.trim(),
      c_PostalCode: document.getElementById('sup-modal-postal').value.trim(),
      c_Country: document.getElementById('sup-modal-country').value.trim(),
      c_Phone: document.getElementById('sup-modal-phone').value.trim()
    };

    if (!body.c_SupplierName || !body.c_ContactName || !body.c_Country || !body.c_Phone) {
      this.toast('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน', 'warning');
      return;
    }

    try {
      if (id) {
        await API.put(`/suppliers/${id}`, body);
        this.toast('แก้ไขข้อมูลผู้จัดจำหน่ายสำเร็จ', 'success');
      } else {
        await API.post('/suppliers', body);
        this.toast('เพิ่มผู้จัดจำหน่ายใหม่สำเร็จ', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
      this.loadSuppliers();
    } catch (err) {
      this.toast('บันทึกไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async deleteSupplier(id, name) {
    if (!confirm(`ต้องการลบผู้จัดจำหน่าย "${name}" (#${id}) ใช่หรือไม่?`)) return;
    try {
      await API.delete(`/suppliers/${id}`);
      this.toast('ลบผู้จัดจำหน่ายเรียบร้อยแล้ว', 'success');
      this.loadSuppliers();
    } catch (err) {
      this.toast('ลบไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 10. EMPLOYEES
  // ═══════════════════════════════════════════════════════
  async loadEmployees() {
    try {
      const res = await API.get('/employees');
      const container = document.getElementById('employees-grid');
      if (!container) return;

      container.innerHTML = res.data.map(e => `
        <div class="col-md-6 col-lg-4 mb-4">
          <div class="card card-bms h-100">
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="brand-icon rounded-circle" style="width:48px;height:48px;font-size:1.2rem">
                  ${this.escape(e.c_FirstName.charAt(0) + e.c_LastName.charAt(0))}
                </div>
                <div>
                  <h6 class="mb-0 fw-bold">${this.escape(e.c_FirstName)} ${this.escape(e.c_LastName)}</h6>
                  <small class="text-muted">รหัสพนักงาน: #${e.i_EmployeeID}</small>
                </div>
              </div>
              <p class="text-muted small mb-2"><i class="bi bi-calendar me-1"></i> วันเกิด: ${this.escape(e.c_BirthDate)}</p>
              <p class="text-muted small mb-3 text-truncate"><i class="bi bi-card-text me-1"></i> ${this.escape(e.c_Notes || '-')}</p>
              <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <span class="badge badge-soft-primary"><i class="bi bi-check2-circle me-1"></i>${e.processed_orders} ออร์เดอร์</span>
                <div>
                  <button class="btn btn-sm btn-outline-primary me-1" onclick="App.openEmployeeModal(${e.i_EmployeeID})">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="App.deleteEmployee(${e.i_EmployeeID}, '${this.escape(e.c_FirstName)}')">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `).join('');
    } catch (err) {
      this.toast('โหลดข้อมูลพนักงานไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async openEmployeeModal(id = null) {
    const form = document.getElementById('employee-modal-form');
    form.reset();
    document.getElementById('emp-modal-id').value = id || '';
    document.getElementById('emp-modal-title').textContent = id ? `แก้ไขข้อมูลพนักงาน #${id}` : 'เพิ่มพนักงานใหม่';

    if (id) {
      try {
        const res = await API.get(`/employees/${id}`);
        const e = res.data;
        document.getElementById('emp-modal-first').value = e.c_FirstName;
        document.getElementById('emp-modal-last').value = e.c_LastName;
        document.getElementById('emp-modal-birth').value = e.c_BirthDate;
        document.getElementById('emp-modal-notes').value = e.c_Notes || '';
      } catch (err) {
        this.toast('ดึงข้อมูลพนักงานไม่สำเร็จ: ' + err.message, 'danger');
        return;
      }
    }

    new bootstrap.Modal(document.getElementById('employeeModal')).show();
  },

  async saveEmployeeModal() {
    const id = document.getElementById('emp-modal-id').value;
    const body = {
      c_FirstName: document.getElementById('emp-modal-first').value.trim(),
      c_LastName: document.getElementById('emp-modal-last').value.trim(),
      c_BirthDate: document.getElementById('emp-modal-birth').value.trim(),
      c_Notes: document.getElementById('emp-modal-notes').value.trim()
    };

    if (!body.c_FirstName || !body.c_LastName || !body.c_BirthDate) {
      this.toast('กรุณากรอกชื่อ นามสกุล และวันเกิด', 'warning');
      return;
    }

    try {
      if (id) {
        await API.put(`/employees/${id}`, body);
        this.toast('แก้ไขข้อมูลพนักงานสำเร็จ', 'success');
      } else {
        await API.post('/employees', body);
        this.toast('เพิ่มพนักงานใหม่สำเร็จ', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
      this.loadEmployees();
    } catch (err) {
      this.toast('บันทึกไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async deleteEmployee(id, name) {
    if (!confirm(`ต้องการลบข้อมูลพนักงาน "${name}" (#${id}) ใช่หรือไม่?`)) return;
    try {
      await API.delete(`/employees/${id}`);
      this.toast('ลบพนักงานเรียบร้อยแล้ว', 'success');
      this.loadEmployees();
    } catch (err) {
      this.toast('ลบไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 11. SHIPPERS
  // ═══════════════════════════════════════════════════════
  async loadShippers() {
    try {
      const res = await API.get('/shippers');
      const container = document.getElementById('shippers-grid');
      if (!container) return;

      container.innerHTML = res.data.map(s => `
        <div class="col-md-4 mb-4">
          <div class="card card-bms h-100">
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="kpi-icon-box" style="background:#eff6ff;color:#2563eb;font-size:1.5rem">
                  🚚
                </div>
                <div>
                  <h6 class="mb-0 fw-bold">${this.escape(s.c_ShipperName)}</h6>
                  <small class="text-muted">รหัสบริษัทขนส่ง: #${s.i_ShipperID}</small>
                </div>
              </div>
              <p class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i> เบอร์โทรศัพท์: <strong>${this.escape(s.c_Phone)}</strong></p>
              <p class="text-muted small mb-3"><i class="bi bi-box-seam me-1"></i> จำนวนครั้งที่จัดส่ง: <span class="badge badge-soft-success">${s.total_shipments} ออร์เดอร์</span></p>
              <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                <button class="btn btn-sm btn-outline-primary" onclick="App.openShipperModal(${s.i_ShipperID}, '${this.escape(s.c_ShipperName)}', '${this.escape(s.c_Phone)}')">
                  <i class="bi bi-pencil"></i> แก้ไข
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="App.deleteShipper(${s.i_ShipperID}, '${this.escape(s.c_ShipperName)}')">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      `).join('');
    } catch (err) {
      this.toast('โหลดบริษัทขนส่งไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  openShipperModal(id = null, name = '', phone = '') {
    document.getElementById('ship-modal-id').value = id || '';
    document.getElementById('ship-modal-name').value = name;
    document.getElementById('ship-modal-phone').value = phone;
    document.getElementById('ship-modal-title').textContent = id ? `แก้ไขบริษัทขนส่ง #${id}` : 'เพิ่มบริษัทขนส่งใหม่';
    new bootstrap.Modal(document.getElementById('shipperModal')).show();
  },

  async saveShipperModal() {
    const id = document.getElementById('ship-modal-id').value;
    const name = document.getElementById('ship-modal-name').value.trim();
    const phone = document.getElementById('ship-modal-phone').value.trim();

    if (!name || !phone) {
      this.toast('กรุณากรอกชื่อบริษัทขนส่งและเบอร์โทรศัพท์', 'warning');
      return;
    }

    try {
      if (id) {
        await API.put(`/shippers/${id}`, { c_ShipperName: name, c_Phone: phone });
        this.toast('แก้ไขบริษัทขนส่งเรียบร้อยแล้ว', 'success');
      } else {
        await API.post('/shippers', { c_ShipperName: name, c_Phone: phone });
        this.toast('เพิ่มบริษัทขนส่งใหม่เรียบร้อยแล้ว', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('shipperModal')).hide();
      this.loadShippers();
    } catch (err) {
      this.toast('บันทึกไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async deleteShipper(id, name) {
    if (!confirm(`ต้องการลบบริษัทขนส่ง "${name}" ใช่หรือไม่?`)) return;
    try {
      await API.delete(`/shippers/${id}`);
      this.toast('ลบบริษัทขนส่งเรียบร้อยแล้ว', 'success');
      this.loadShippers();
    } catch (err) {
      this.toast('ลบไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // 12. REPORTS & EXPORT
  // ═══════════════════════════════════════════════════════
  async loadReports() {
    try {
      const res = await API.get('/reports/sales');
      const data = res.data;

      // Render Category Report Table
      const catTbody = document.getElementById('report-category-tbody');
      if (catTbody) {
        catTbody.innerHTML = data.by_category.map(c => `
          <tr>
            <td class="fw-semibold">${this.escape(c.c_CategoryName)}</td>
            <td class="text-center">${c.product_count}</td>
            <td class="text-end">${Number(c.total_units_sold).toLocaleString()}</td>
            <td class="text-end fw-bold text-primary">฿${Number(c.total_sales).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
          </tr>
        `).join('');
      }

      // Render Shipper Report Table
      const shipTbody = document.getElementById('report-shipper-tbody');
      if (shipTbody) {
        shipTbody.innerHTML = data.by_shipper.map(s => `
          <tr>
            <td class="fw-semibold">${this.escape(s.c_ShipperName)}</td>
            <td class="text-center">${s.total_orders} ครั้ง</td>
            <td class="text-end fw-bold text-success">฿${Number(s.total_revenue).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
          </tr>
        `).join('');
      }

      // Render Employee Performance
      const empTbody = document.getElementById('report-employee-tbody');
      if (empTbody) {
        empTbody.innerHTML = data.by_employee.map(e => `
          <tr>
            <td class="fw-semibold">${this.escape(e.employee_name)}</td>
            <td class="text-center">${e.orders_handled} ออร์เดอร์</td>
            <td class="text-end fw-bold text-primary">฿${Number(e.total_sales).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</td>
          </tr>
        `).join('');
      }

    } catch (err) {
      this.toast('โหลดรายงานไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  async exportReport(type) {
    const allowedTypes = ['products', 'orders', 'categories'];
    if (!allowedTypes.includes(type)) {
      this.toast('ประเภทรายงานไม่ถูกต้อง', 'danger');
      return;
    }

    try {
      const file = await API.get('/reports/export', { type });
      const objectUrl = URL.createObjectURL(file);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = `northwind-${type}-report.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
      this.toast(`ดาวน์โหลดรายงาน ${type} สำเร็จ`, 'success');
    } catch (err) {
      this.toast('ดาวน์โหลดรายงานไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  // ═══════════════════════════════════════════════════════
  // UTILITY HELPERS
  // ═══════════════════════════════════════════════════════
  async changePassword(event) {
    event.preventDefault();
    const form = document.getElementById('security-password-form');
    const currentPassword = document.getElementById('security-current-password').value;
    const newPassword = document.getElementById('security-new-password').value;
    const confirmPassword = document.getElementById('security-confirm-password').value;
    const newPasswordBytes = new TextEncoder().encode(newPassword).length;

    if (newPasswordBytes < 12 || newPasswordBytes > 72) {
      this.toast('รหัสผ่านใหม่ต้องมีความยาว 12 ถึง 72 ไบต์', 'warning');
      return;
    }
    if (newPassword !== confirmPassword) {
      this.toast('รหัสผ่านใหม่และคำยืนยันไม่ตรงกัน', 'warning');
      return;
    }

    try {
      await API.put('/auth/password', {
        current_password: currentPassword,
        new_password: newPassword
      });
      form.reset();
      API.setToken(null);
      API.setUser(null);
      window.location.hash = 'login';
      this.showScreen('login');
      this.toast('เปลี่ยนรหัสผ่านสำเร็จ', 'success');
    } catch (err) {
      this.toast('เปลี่ยนรหัสผ่านไม่สำเร็จ: ' + err.message, 'danger');
    }
  },

  renderPagination(containerId, meta, onPageClick) {
    const container = document.getElementById(containerId);
    if (!container || !meta) return;

    const { current_page, total_pages, total_records } = meta;

    if (total_pages <= 1) {
      container.innerHTML = `<small class="text-muted">ทั้งหมด ${total_records} รายการ</small>`;
      return;
    }

    let html = `<div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
      <small class="text-muted">แสดงหน้า ${current_page} จากทั้งหมด ${total_pages} หน้า (${total_records} รายการ)</small>
      <nav><ul class="pagination pagination-sm mb-0">`;

    // Prev
    html += `<li class="page-item ${current_page <= 1 ? 'disabled' : ''}">
      <button class="page-link" onclick="App.handlePageClick('${containerId}', ${current_page - 1})">&laquo;</button>
    </li>`;

    // Page numbers
    const start = Math.max(1, current_page - 2);
    const end = Math.min(total_pages, current_page + 2);
    for (let i = start; i <= end; i++) {
      html += `<li class="page-item ${i === current_page ? 'active' : ''}">
        <button class="page-link" onclick="App.handlePageClick('${containerId}', ${i})">${i}</button>
      </li>`;
    }

    // Next
    html += `<li class="page-item ${current_page >= total_pages ? 'disabled' : ''}">
      <button class="page-link" onclick="App.handlePageClick('${containerId}', ${current_page + 1})">&raquo;</button>
    </li>`;

    html += `</ul></nav></div>`;
    container.innerHTML = html;

    // Attach handler
    this._paginationCallbacks = this._paginationCallbacks || {};
    this._paginationCallbacks[containerId] = onPageClick;
  },

  handlePageClick(containerId, page) {
    if (this._paginationCallbacks && this._paginationCallbacks[containerId]) {
      this._paginationCallbacks[containerId](page);
    }
  },

  toast(message, type = 'info') {
    const toastEl = document.getElementById('global-toast');
    if (!toastEl) {
      alert(message);
      return;
    }
    const bodyEl = document.getElementById('global-toast-body');
    const headerEl = document.getElementById('global-toast-header');

    bodyEl.textContent = message;
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;

    const bsToast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4000 });
    bsToast.show();
  },

  debounce(func, wait) {
    let timeout;
    const self = this;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(self, args), wait);
    };
  },

  escape(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
};

window.App = App;

document.addEventListener('DOMContentLoaded', () => {
  App.init();
});
