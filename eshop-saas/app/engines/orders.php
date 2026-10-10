<?php
declare(strict_types=1);

const ORDER_STATUSES = ['pending' => 'Pending', 'processing' => 'Processing', 'on_hold' => 'On hold', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'];
const PAYMENT_STATUSES = ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'];
const SHIPPING_STATUSES = ['not_shipped' => 'Not shipped', 'preparing' => 'Preparing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'returned' => 'Returned'];

/* ---------- Cart (session, per shop; prices always recomputed server-side) ---------- */
function cart_raw(int $shopId): array { return arr($_SESSION['cart'][$shopId] ?? []); }
function cart_set_raw(int $shopId, array $items): void { $_SESSION['cart'][$shopId] = $items; }
function cart_key(int $pid, int $vid): string { return $pid . ':' . $vid; }

function cart_add(array $shop, int $pid, int $vid, int $qty): array {
    $sid = (int) $shop['id'];
    $line = cart_resolve_line($shop, $pid, $vid);
    if (!$line) throw new UserError('This product is not available.');
    if ($line['has_variants'] && !$vid) throw new UserError(t('choose_option') . ': ' . t('variant'));
    $items = cart_raw($sid);
    $k = cart_key($pid, $vid);
    $new = min(99, max(1, ($items[$k] ?? 0) + max(1, $qty)));
    if ($line['max'] !== null && $new > $line['max']) {
        if ($line['max'] <= 0) throw new UserError(t('out_of_stock'));
        $new = $line['max'];
    }
    $items[$k] = $new;
    cart_set_raw($sid, $items);
    return cart_summary($shop);
}
function cart_update(array $shop, string $key, int $qty): void {
    $sid = (int) $shop['id'];
    $items = cart_raw($sid);
    if (!isset($items[$key])) return;
    if ($qty <= 0) unset($items[$key]); else $items[$key] = min(99, $qty);
    cart_set_raw($sid, $items);
}

/** Look up a purchasable line: only visible (active, in-quota) products. */
function cart_resolve_line(array $shop, int $pid, int $vid): ?array {
    [$w, $pp] = visible_where($shop);
    $p = row("SELECT p.* FROM products p WHERE p.id = ? AND $w", array_merge([$pid], $pp));
    if (!$p) return null;
    $hasVariants = (bool) val('SELECT 1 FROM product_variants WHERE product_id = ? LIMIT 1', [$pid]);
    $v = null;
    if ($vid) { $v = row('SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND shop_id = ?', [$vid, $pid, $shop['id']]); if (!$v) return null; }
    $price = product_effective_price($p);
    if ($v && $v['price_cents'] !== null) $price = (int) $v['price_cents'];
    $max = (int) $p['manage_stock'] ? ($v ? (int) $v['stock'] : (int) $p['stock']) : null;
    return ['product' => $p, 'variant' => $v, 'has_variants' => $hasVariants, 'price' => $price, 'max' => $max];
}

function cart_summary(array $shop): array {
    $sid = (int) $shop['id'];
    $items = cart_raw($sid); $lines = []; $subtotal = 0; $count = 0; $removed = false;
    foreach ($items as $k => $qty) {
        [$pid, $vid] = array_map('intval', explode(':', (string) $k) + [0, 0]);
        $l = cart_resolve_line($shop, $pid, $vid);
        if (!$l || ($l['max'] !== null && $l['max'] <= 0)) { unset($items[$k]); $removed = true; continue; }
        if ($l['max'] !== null && $qty > $l['max']) { $qty = $l['max']; $items[$k] = $qty; }
        $p = products_attach_images([$l['product']])[0];
        $lines[] = ['key' => $k, 'product' => $p, 'variant' => $l['variant'], 'qty' => (int) $qty, 'price' => $l['price'], 'total' => $l['price'] * (int) $qty, 'max' => $l['max']];
        $subtotal += $l['price'] * (int) $qty; $count += (int) $qty;
    }
    if ($removed) { cart_set_raw($sid, $items); }
    return ['lines' => $lines, 'subtotal' => $subtotal, 'count' => $count, 'removed' => $removed];
}

/* ---------- Shipping & payment options (data from shop settings) ---------- */
function shipping_options(array $shop, int $subtotal): array {
    $sid = (int) $shop['id'];
    $flat = (int) shop_setting($sid, 'shipping_flat_cents', '0');
    $free = (int) shop_setting($sid, 'free_shipping_over_cents', '0');
    $opts = ['courier' => ['label' => $shop['locale'] === 'el' ? 'Αποστολή με courier' : 'Courier delivery', 'cost' => ($free > 0 && $subtotal >= $free) ? 0 : $flat]];
    if (shop_setting($sid, 'pickup_enabled') === '1') $opts['pickup'] = ['label' => $shop['locale'] === 'el' ? 'Παραλαβή από το κατάστημα' : 'Store pickup', 'cost' => 0];
    return $opts;
}
function payment_options(array $shop): array {
    $sid = (int) $shop['id'];
    $el = $shop['locale'] === 'el';
    $o = [];
    if (shop_setting($sid, 'payment_cod') === '1') $o['cod'] = ['label' => $el ? 'Αντικαταβολή' : 'Cash on delivery', 'fee' => (int) shop_setting($sid, 'cod_fee_cents', '0')];
    if (shop_setting($sid, 'payment_bank') === '1') $o['bank_transfer'] = ['label' => $el ? 'Τραπεζική κατάθεση' : 'Bank transfer', 'fee' => 0];
    if (!$o) $o['cod'] = ['label' => $el ? 'Αντικαταβολή' : 'Cash on delivery', 'fee' => 0];
    return $o;
}

/** Validate and place an order atomically (stock checked and decremented inside the transaction). */
function order_place(array $shop, array $in, ?array $customer): array {
    $sid = (int) $shop['id'];
    $errors = [];
    $email = mb_strtolower(clean_text($in['email'] ?? '', 190));
    $name = clean_text($in['name'] ?? '', 120);
    $phone = clean_text($in['phone'] ?? '', 40);
    $addr = ['address' => clean_text($in['address'] ?? '', 200), 'city' => clean_text($in['city'] ?? '', 100), 'postcode' => clean_text($in['postcode'] ?? '', 20), 'country' => clean_text($in['country'] ?? 'GR', 60)];
    if (!valid_email($email)) $errors['email'] = true;
    if ($name === '') $errors['name'] = true;
    if ($phone === '') $errors['phone'] = true;
    $cart = cart_summary($shop);
    $ship = shipping_options($shop, $cart['subtotal']);
    $pay = payment_options($shop);
    $shipKey = (string) ($in['shipping_method'] ?? array_key_first($ship));
    $payKey = (string) ($in['payment_method'] ?? array_key_first($pay));
    if (!isset($ship[$shipKey])) $errors['shipping_method'] = true;
    if (!isset($pay[$payKey])) $errors['payment_method'] = true;
    if ($shipKey !== 'pickup') foreach (['address', 'city', 'postcode'] as $f) if ($addr[$f] === '') $errors[$f] = true;
    if (empty($in['terms'])) $errors['terms'] = true;
    if (!$cart['lines']) throw new UserError(t('empty_cart'));
    if ($errors) throw new ValidationError(array_map(static fn() => t('required'), $errors));

    return tx(static function () use ($shop, $sid, $email, $name, $phone, $addr, $in, $customer, $ship, $pay, $shipKey, $payKey) {
        $cart = cart_summary($shop); // recompute inside the transaction
        foreach ($cart['lines'] as $l) {
            if ($l['max'] !== null && $l['qty'] > $l['max']) throw new UserError(t('out_of_stock') . ': ' . $l['product']['name']);
        }
        $shipping = (int) $ship[$shipKey]['cost'];
        $fee = (int) $pay[$payKey]['fee'];
        $total = $cart['subtotal'] + $shipping + $fee;
        $num = (int) val('SELECT next_order_number FROM shops WHERE id = ?', [$sid]);
        q('UPDATE shops SET next_order_number = next_order_number + 1 WHERE id = ?', [$sid]);
        $custId = $customer['id'] ?? null;
        if (!$custId) {
            $ex = row('SELECT id FROM customers WHERE shop_id = ? AND email = ?', [$sid, $email]);
            $custId = $ex ? (int) $ex['id'] : insert('customers', ['shop_id' => $sid, 'email' => $email, 'name' => $name, 'phone' => $phone, 'address' => jenc($addr), 'created_at' => now()]);
        }
        update('customers', ['name' => $name, 'phone' => $phone, 'address' => jenc($addr), 'updated_at' => now()], 'id = ? AND shop_id = ?', [$custId, $sid]);
        $token = random_token(16);
        $oid = insert('orders', [
            'shop_id' => $sid, 'number' => (string) $num, 'access_token' => $token, 'customer_id' => $custId, 'email' => $email, 'name' => $name, 'phone' => $phone,
            'billing' => jenc($addr + ['name' => $name]), 'shipping' => jenc($addr + ['name' => $name]),
            'subtotal_cents' => $cart['subtotal'], 'shipping_cents' => $shipping, 'fee_cents' => $fee, 'total_cents' => $total, 'currency' => $shop['currency'],
            'shipping_method' => $ship[$shipKey]['label'], 'payment_method' => $payKey, 'notes' => clean_multiline($in['notes'] ?? '', 1000), 'ip_hash' => ip_hash(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($cart['lines'] as $l) {
            $p = $l['product']; $v = $l['variant'];
            insert('order_items', ['order_id' => $oid, 'shop_id' => $sid, 'product_id' => $p['id'], 'variant_id' => $v['id'] ?? null, 'name' => $p['name'], 'sku' => $v['sku'] ?? $p['sku'],
                'options' => $v ? variant_label(jdec($v['options'])) : null, 'price_cents' => $l['price'], 'quantity' => $l['qty'], 'total_cents' => $l['total']]);
            if ((int) $p['manage_stock']) {
                if ($v) q('UPDATE product_variants SET stock = MAX(0, stock - ?) WHERE id = ?', [$l['qty'], $v['id']]);
                q('UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ? AND shop_id = ?', [$l['qty'], $p['id'], $sid]);
            }
        }
        insert('order_history', ['order_id' => $oid, 'shop_id' => $sid, 'status' => 'pending', 'note' => 'Order placed', 'actor' => 'customer', 'created_at' => now()]);
        cart_set_raw($sid, []);
        $order = row('SELECT * FROM orders WHERE id = ?', [$oid]);
        $lines = implode("\n", array_map(static fn($l) => '- ' . $l['product']['name'] . ($l['variant'] ? ' (' . variant_label(jdec($l['variant']['options'])) . ')' : '') . ' x' . $l['qty'] . ' = ' . money($l['total'], $shop['locale']), $cart['lines']));
        $body = t('order_number') . ": #$num\n\n$lines\n\n" . t('total') . ': ' . money($total, $shop['locale']) . "\n";
        send_mail($email, $shop['name'] . ' — ' . t('order') . " #$num", $body);
        $notifyTo = (string) shop_setting($sid, 'order_email', $shop['email']);
        if ($notifyTo) send_mail($notifyTo, "New order #$num", "New order #$num from $name <$email>\n\n$body");
        notify('order.new', "New order #$num", money($total, 'en') . " from $name", $sid, 'shop', 'ok');
        return $order;
    }, true);
}

function order_update(int $shopId, int $orderId, array $in): void {
    $o = owned('orders', $orderId, $shopId);
    $data = [];
    foreach (['status' => ORDER_STATUSES, 'payment_status' => PAYMENT_STATUSES, 'shipping_status' => SHIPPING_STATUSES] as $f => $allowed) if (isset($in[$f], $allowed[$in[$f]])) $data[$f] = $in[$f];
    $data['tracking_number'] = clean_text($in['tracking_number'] ?? $o['tracking_number'] ?? '', 80) ?: null;
    $data['admin_notes'] = clean_multiline($in['admin_notes'] ?? $o['admin_notes'] ?? '', 2000);
    $data['updated_at'] = now();
    update('orders', $data, 'id = ? AND shop_id = ?', [$orderId, $shopId]);
    $changes = [];
    foreach (['status', 'payment_status', 'shipping_status'] as $f) if (isset($data[$f]) && $data[$f] !== $o[$f]) $changes[] = "$f: {$o[$f]} → {$data[$f]}";
    $note = clean_text($in['history_note'] ?? '', 300);
    if ($changes || $note) insert('order_history', ['order_id' => $orderId, 'shop_id' => $shopId, 'status' => $data['status'] ?? $o['status'], 'note' => trim(implode('; ', $changes) . ' ' . $note), 'actor' => current_user()['email'] ?? 'admin', 'created_at' => now()]);
    audit('order.updated', 'order', $orderId, $shopId, 'success', ['changes' => $changes]);
}

/* ---------- Customer accounts ---------- */
function customer_current(int $shopId): ?array {
    $cid = (int) ($_SESSION['customer'][$shopId] ?? 0);
    return $cid ? row('SELECT * FROM customers WHERE id = ? AND shop_id = ? AND deleted_at IS NULL', [$cid, $shopId]) : null;
}
function customer_login(int $shopId, string $email, string $pw): bool {
    if (!rate_limit('cust_login_' . $shopId, 15, 900)) throw new UserError('Too many attempts. Try again later.');
    $c = row('SELECT * FROM customers WHERE shop_id = ? AND email = ? AND deleted_at IS NULL', [$shopId, mb_strtolower(trim($email))]);
    if (!$c || !$c['password_hash']) return false;
    if ($c['locked_until'] && strtotime($c['locked_until']) > time()) throw new UserError('Account temporarily locked. Try again in 15 minutes.');
    if (!password_verify($pw, $c['password_hash'])) {
        $f = (int) $c['failed_attempts'] + 1;
        update('customers', ['failed_attempts' => $f >= 5 ? 0 : $f, 'locked_until' => $f >= 5 ? date('Y-m-d H:i:s', time() + 900) : null], 'id = ?', [$c['id']]);
        return false;
    }
    update('customers', ['failed_attempts' => 0, 'locked_until' => null], 'id = ?', [$c['id']]);
    if (PHP_SAPI !== 'cli') session_regenerate_id(true);
    $_SESSION['customer'][$shopId] = (int) $c['id'];
    return true;
}
