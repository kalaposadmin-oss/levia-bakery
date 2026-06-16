const productsRaw = Array.isArray(window.LEVIA_PRODUCTS) ? window.LEVIA_PRODUCTS : [];
const fallbackProducts = [
  { id: 'fallback-1', name: 'Almond Croissant', description: 'Pastry lapis dengan almond cream.', ingredients: 'Tepung terigu, butter, almond, telur, gula', package_info: '1 pcs / 85 gram', shelf_life: '1 hari suhu ruang', price: 28000, stock: 24, stockStatus: 'ready', category: 'croissant', tags: ['popular'], image: 'assets/almond-croissant.png', badge: 'Ready Stock' },
  { id: 'fallback-2', name: 'Cinnamon Roll', description: 'Roti gulung kayu manis dan glaze lembut.', ingredients: 'Tepung terigu, kayu manis, butter, gula, susu', package_info: '1 pcs / 95 gram', shelf_life: '2 hari suhu ruang', price: 22000, stock: 18, stockStatus: 'ready', category: 'roti-manis', tags: ['popular'], image: 'assets/cinnamon-roll.png', badge: 'Ready Stock' },
  { id: 'fallback-3', name: 'Seeded Bread', description: 'Roti biji-bijian untuk sarapan.', ingredients: 'Tepung gandum, biji bunga matahari, wijen, ragi', package_info: '1 loaf / 450 gram', shelf_life: '3 hari suhu ruang', price: 15000, stock: 12, stockStatus: 'ready', category: 'roti-tawar', tags: [], image: 'assets/seeded-bread.png', badge: 'Ready Stock' }
];
const products = (productsRaw.length ? productsRaw : fallbackProducts).map((product) => ({
  id: String(product.id),
  name: product.name,
  price: Number(product.price),
  stock: Number(product.stock),
  stockStatus: product.stock_status || product.stockStatus || "ready",
  description: product.description || "",
  ingredients: product.ingredients || "",
  package_info: product.package_info || product.packageInfo || "",
  shelf_life: product.shelf_life || product.shelfLife || "",
  category: product.category_slug || "lainnya",
  categoryName: product.category_name || product.categoryName || "",
  tags: [
    product.is_popular === 1 || product.is_popular === "1" ? "popular" : "",
    product.category_slug === "promo" ? "promo" : ""
  ].filter(Boolean),
  image: product.image || "assets/almond-croissant.png",
  badge: (product.stock_status || product.stockStatus) === "limited" ? "Terbatas" : (product.stock_status || product.stockStatus) === "sold_out" ? "Habis" : "Ready"
}));

const rupiah = new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 });
const deliveryOptions = Array.isArray(window.LEVIA_DELIVERY_OPTIONS) && window.LEVIA_DELIVERY_OPTIONS.length
  ? window.LEVIA_DELIVERY_OPTIONS
  : [
      { value: "pickup", label: "Ambil Sendiri", needs_address: false, maps_url: "" },
      { value: "home_bake", label: "Home Bake", needs_address: true, maps_url: "" },
      { value: "gosend", label: "Gosend / Gojek", needs_address: true, maps_url: "" }
    ];

const state = {
  cart: JSON.parse(localStorage.getItem("leviaCartDb") || "{}"),
  orders: [],
  category: "all",
  query: ""
};

const bestSellerList = document.querySelector("#bestSellerList");
const stockList = document.querySelector("#stockList");
const searchInput = document.querySelector("#searchInput");
const cartDrawer = document.querySelector("#cartDrawer");
const cartItems = document.querySelector("#cartItems");
const cartTotal = document.querySelector("#cartTotal");
const toast = document.querySelector("#toast");
const checkoutBtn = document.querySelector("#checkoutBtn");
const checkoutForm = document.querySelector("#checkoutForm");
const cartPayload = document.querySelector("#cartPayload");
const deliveryMethod = document.querySelector("#deliveryMethod");
const deliveryAddress = document.querySelector("#deliveryAddress");
const deliveryMapsLink = document.querySelector("#deliveryMapsLink");
const productModal = document.querySelector("#productModal");
const productDetailImage = document.querySelector("#productDetailImage");
const productDetailCategory = document.querySelector("#productDetailCategory");
const productDetailName = document.querySelector("#productDetailName");
const productDetailBadge = document.querySelector("#productDetailBadge");
const productDetailPrice = document.querySelector("#productDetailPrice");
const productDetailDescription = document.querySelector("#productDetailDescription");
const productDetailIngredients = document.querySelector("#productDetailIngredients");
const productDetailPackage = document.querySelector("#productDetailPackage");
const productDetailStock = document.querySelector("#productDetailStock");
const productDetailShelfLife = document.querySelector("#productDetailShelfLife");
const productDetailAddBtn = document.querySelector("#productDetailAddBtn");

function money(value) {
  return rupiah.format(value).replace(/\s/g, " ");
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"]/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;"
  }[char]));
}

function saveCart() {
  localStorage.setItem("leviaCartDb", JSON.stringify(state.cart));
}

function getProduct(id) {
  return products.find((product) => product.id === String(id));
}

function getDeliveryOption(value) {
  return deliveryOptions.find((option) => option.value === value) || deliveryOptions[0] || null;
}

function cartLines() {
  return Object.entries(state.cart)
    .map(([id, qty]) => ({ product: getProduct(id), qty: Number(qty) }))
    .filter((line) => line.product && line.qty > 0);
}

function cartStats() {
  return cartLines().reduce((acc, line) => {
    acc.items += line.qty;
    acc.total += line.qty * line.product.price;
    return acc;
  }, { items: 0, total: 0 });
}

function showToast(message) {
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove("is-visible"), 2400);
}

function productCard(product, compact = false) {
  const isSoldOut = product.stockStatus === "sold_out";
  const badgeClass = product.badge === "Terbatas" ? "badge limited" : product.badge === "Habis" ? "badge sold-out" : "badge ready";
  const buttonLabel = isSoldOut ? "Habis" : compact ? "Tambah" : "+ Tambah";
  return `
    <article class="product-card" data-product-id="${escapeHtml(product.id)}" tabindex="0" role="button" aria-label="Lihat detail ${escapeHtml(product.name)}">
      <div class="product-image">
        <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}">
        <span class="${badgeClass}">${escapeHtml(product.badge)}</span>
      </div>
      <div class="product-body">
        <h3 title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</h3>
        <p>${money(product.price)}</p>
        <button class="add-button" type="button" data-add="${product.id}" ${isSoldOut ? "disabled" : ""}>${buttonLabel}</button>
      </div>
    </article>
  `;
}

function stockText(product) {
  if (product.stockStatus === "sold_out") return "Habis";
  if (product.stockStatus === "limited") return product.stock > 0 ? `Terbatas, ${product.stock} pcs` : "Terbatas";
  return product.stock > 0 ? `Ready, ${product.stock} pcs` : "Ready stock";
}

function ingredientItems(value) {
  return String(value || "")
    .split(/\r?\n|\s*,\s*/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function openProductDetail(id) {
  const product = getProduct(id);
  if (!product || !productModal) return;

  productDetailImage.src = product.image;
  productDetailImage.alt = product.name;
  productDetailCategory.textContent = product.categoryName || product.category || "Produk";
  productDetailName.textContent = product.name;
  productDetailBadge.textContent = product.badge;
  productDetailBadge.className = product.badge === "Terbatas" ? "badge limited" : product.badge === "Habis" ? "badge sold-out" : "badge ready";
  productDetailPrice.textContent = money(product.price);
  productDetailDescription.textContent = product.description || "Deskripsi produk belum diisi.";
  const ingredients = ingredientItems(product.ingredients);
  productDetailIngredients.innerHTML = ingredients.length
    ? ingredients.map((item) => `<li>${escapeHtml(item)}</li>`).join("")
    : "<li>Belum diisi</li>";
  productDetailPackage.textContent = product.package_info || product.packageInfo || "Belum diisi";
  productDetailStock.textContent = stockText(product);
  productDetailShelfLife.textContent = product.shelf_life || product.shelfLife || "Belum diisi";
  productDetailAddBtn.dataset.add = product.id;
  productDetailAddBtn.disabled = product.stockStatus === "sold_out";
  productDetailAddBtn.textContent = product.stockStatus === "sold_out" ? "Stok Habis" : "Tambahkan ke Keranjang";

  productModal.classList.add("is-open");
  productModal.setAttribute("aria-hidden", "false");
}

function closeProductDetail() {
  if (!productModal) return;
  productModal.classList.remove("is-open");
  productModal.setAttribute("aria-hidden", "true");
}

function filteredProducts() {
  const query = state.query.trim().toLowerCase();
  return products.filter((product) => {
    const categoryMatch = state.category === "all" || product.category === state.category || product.tags.includes(state.category);
    const searchMatch = !query || product.name.toLowerCase().includes(query);
    return categoryMatch && searchMatch;
  });
}

function renderProducts() {
  const popular = products.filter((product) => product.tags.includes("popular")).slice(0, 5);
  const fallbackPopular = popular.length ? popular : products.slice(0, 5);
  const stock = filteredProducts();

  if (bestSellerList) {
    bestSellerList.innerHTML = fallbackPopular.length
      ? fallbackPopular.map((product) => productCard(product, true)).join("")
      : '<div class="empty-state">Produk favorit belum tersedia.</div>';
  }

  if (stockList) {
    stockList.innerHTML = stock.length
      ? stock.map((product) => productCard(product)).join("")
      : '<div class="empty-state">Menu tidak ditemukan.</div>';
  }
}

function renderDeliveryOptions() {
  if (!deliveryMethod) return;
  const currentValue = deliveryMethod.value || 'pickup';
  deliveryMethod.innerHTML = deliveryOptions.map((option) => `<option value="${option.value}">${option.label}</option>`).join("");
  deliveryMethod.value = deliveryOptions.some((option) => option.value === currentValue) ? currentValue : (deliveryOptions[0]?.value || 'pickup');
}

function syncDeliveryAddress() {
  if (!deliveryMethod || !deliveryAddress) return;
  const option = getDeliveryOption(deliveryMethod.value);
  if (!option) return;

  deliveryAddress.readOnly = !option.needs_address;
  if (!option.needs_address) {
    deliveryAddress.value = "Ambil sendiri di toko";
  } else if (!deliveryAddress.value || deliveryAddress.value === "Ambil sendiri di toko") {
    deliveryAddress.value = "Tulis alamat / titik antar";
  }

  if (!deliveryMapsLink) return;
  if (option.maps_url) {
    deliveryMapsLink.href = option.maps_url;
    deliveryMapsLink.textContent = "Buka link Google Maps";
    deliveryMapsLink.hidden = false;
    return;
  }

  deliveryMapsLink.removeAttribute("href");
  deliveryMapsLink.textContent = "";
  deliveryMapsLink.hidden = true;
}

function checkoutSummaryText(orderCode, stats, formData) {
  const option = getDeliveryOption(String(formData.get("delivery_method") || ""));
  const lines = cartLines()
    .map((line) => `- ${line.product.name} x${line.qty} = ${money(line.qty * line.product.price)}`)
    .join("\n");

  return [
    `Halo ${window.LEVIA_STORE_NAME || "Levia Bakery"}, saya mau tanya ketersediaan menu ini.`,
    `Kode request: ${orderCode}`,
    "",
    `Nama: ${formData.get("customer_name") || "-"}`,
    `No. WhatsApp: ${formData.get("customer_phone") || "-"}`,
    `Metode: ${option?.label || formData.get("delivery_method") || "-"}`,
    `Alamat: ${formData.get("delivery_address") || "-"}`,
    `Catatan: ${formData.get("note") || "-"}`,
    "",
    "Request menu:",
    lines,
    "",
    `Total: ${money(stats.total)}`
  ].join("\n");
}

function openAdminWhatsApp(orderCode, stats, formData) {
  const phone = String(window.LEVIA_ADMIN_WHATSAPP || "").replace(/\D+/g, "");
  if (!phone) {
    showToast(`Request ${orderCode} tersimpan. Nomor WhatsApp toko belum diset.`);
    return;
  }

  const message = encodeURIComponent(checkoutSummaryText(orderCode, stats, formData));
  window.location.href = `https://wa.me/${phone}?text=${message}`;
}

function renderCart() {
  const lines = cartLines();
  const stats = cartStats();

  document.querySelectorAll("[data-cart-count]").forEach((element) => {
    element.textContent = stats.items;
  });

  if (cartTotal) {
    cartTotal.textContent = money(stats.total);
  }

  if (cartPayload) {
    cartPayload.value = JSON.stringify(lines.map((line) => ({ product_id: line.product.id, qty: line.qty })));
  }

  if (cartItems) {
    cartItems.innerHTML = lines.length
      ? lines.map((line) => `
        <article class="cart-line">
          <img src="${line.product.image}" alt="${line.product.name}">
          <div><h3>${line.product.name}</h3><p>${money(line.product.price)}</p></div>
          <div class="qty">
            <button type="button" data-decrement="${line.product.id}">-</button>
            <span>${line.qty}</span>
            <button type="button" data-increment="${line.product.id}">+</button>
          </div>
        </article>
      `).join("")
      : '<div class="empty-state">Keranjang masih kosong.</div>';
  }

  const metricItems = document.querySelector("#metricItems");
  const metricOrders = document.querySelector("#metricOrders");
  const metricRevenue = document.querySelector("#metricRevenue");
  if (metricItems) metricItems.textContent = stats.items;
  if (metricOrders) metricOrders.textContent = state.orders.length;
  if (metricRevenue) metricRevenue.textContent = money(state.orders.reduce((sum, order) => sum + order.total, 0));
  if (checkoutBtn) checkoutBtn.disabled = stats.items === 0 || checkoutBtn.dataset.storeClosed === '1';
}

function setCategory(category) {
  state.category = category;
  document.querySelectorAll(".category").forEach((button) => {
    button.classList.toggle("is-active", button.dataset.category === category);
  });
  renderProducts();
}

function addToCart(id, qty = 1) {
  const product = getProduct(id);
  if (!product) return;
  if (product.stockStatus === "sold_out") {
    showToast(`${product.name} sedang habis.`);
    return;
  }

  const current = state.cart[id] || 0;
  if (product.stockStatus === "limited" && product.stock > 0 && current + qty > product.stock) {
    showToast(`${product.name} maksimal ${product.stock} pcs per request.`);
    return;
  }

  state.cart[id] = current + qty;
  saveCart();
  renderCart();
  showToast(`${product.name} masuk request WA.`);
}

function changeQty(id, delta) {
  if (!state.cart[id]) return;
  if (delta > 0) {
    addToCart(id, delta);
    return;
  }

  state.cart[id] += delta;
  if (state.cart[id] <= 0) {
    delete state.cart[id];
  }
  saveCart();
  renderCart();
}

function openCart() {
  if (!cartDrawer) return;
  cartDrawer.classList.add("is-open");
  cartDrawer.setAttribute("aria-hidden", "false");
}

function closeCart() {
  if (!cartDrawer) return;
  cartDrawer.classList.remove("is-open");
  cartDrawer.setAttribute("aria-hidden", "true");
}

async function checkout() {
  const stats = cartStats();
  if (!stats.items) {
    showToast("Pilih menu dulu sebelum lanjut ke WhatsApp.");
    return;
  }
  if (!checkoutForm || !checkoutBtn || !cartPayload) return;
  if (deliveryMethod && !deliveryMethod.value) {
    deliveryMethod.value = 'pickup';
  }
  if (checkoutBtn.dataset.storeClosed === '1') {
    showToast('Toko sedang libur. Request WhatsApp belum bisa diproses.');
    return;
  }

  cartPayload.value = JSON.stringify(cartLines().map((line) => ({ product_id: line.product.id, qty: line.qty })));
  checkoutBtn.disabled = true;
  checkoutBtn.textContent = "Menyiapkan WA...";

  try {
    const payload = new FormData(checkoutForm);
    const response = await fetch(window.LEVIA_ORDER_ENDPOINT, { method: "POST", body: payload });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Request gagal dibuat.");
    }

    state.orders.push({ total: stats.total });
    openAdminWhatsApp(result.order_code, stats, payload);
    state.cart = {};
    saveCart();
    renderCart();
    closeCart();
    showToast(`Request ${result.order_code} dibuat. Lanjut konfirmasi di WhatsApp.`);
  } catch (error) {
    showToast(error.message);
  } finally {
    checkoutBtn.disabled = false;
    checkoutBtn.textContent = "Lanjut ke WhatsApp";
  }
}

document.addEventListener("click", (event) => {
  const target = event.target.closest("button");
  if (!target || target.disabled) return;

  if (target.dataset.add) addToCart(target.dataset.add);
  if (target.dataset.increment) changeQty(target.dataset.increment, 1);
  if (target.dataset.decrement) changeQty(target.dataset.decrement, -1);
  if (target.dataset.category) setCategory(target.dataset.category);
  if (target.dataset.categoryJump) setCategory(target.dataset.categoryJump);
  if (target.dataset.openCart !== undefined) openCart();
  if (target.dataset.closeCart !== undefined) closeCart();
  if (target.dataset.closeProductDetail !== undefined) closeProductDetail();

  if (target.dataset.promo) {
    setCategory("promo");
    showToast(`${target.dataset.promo} aktif.`);
  }

  if (target.id === "storeDetailBtn") {
    showToast("Cabang buka sesuai jam operasional toko.");
  }
});

document.addEventListener("click", (event) => {
  const card = event.target.closest(".product-card[data-product-id]");
  if (!card || event.target.closest("button, a, input, textarea, select")) return;
  openProductDetail(card.dataset.productId);
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Enter" && event.key !== " ") return;
  const card = event.target.closest(".product-card[data-product-id]");
  if (!card) return;
  event.preventDefault();
  openProductDetail(card.dataset.productId);
});

if (searchInput) {
  searchInput.addEventListener("input", (event) => {
    state.query = event.target.value;
    renderProducts();
  });
}

if (deliveryMethod) {
  deliveryMethod.addEventListener("change", syncDeliveryAddress);
}

if (checkoutBtn) {
  checkoutBtn.dataset.storeClosed = checkoutBtn.disabled && checkoutBtn.textContent.includes('Toko Libur') ? '1' : '0';
  checkoutBtn.addEventListener("click", checkout);
}

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeCart();
    closeProductDetail();
  }
});

renderDeliveryOptions();
syncDeliveryAddress();
renderProducts();
renderCart();
