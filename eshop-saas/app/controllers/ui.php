<?php
declare(strict_types=1);

/** Back-office form helpers: consistent, accessible, labelled fields with inline errors. */
function form_errors(): array { $e = arr($_SESSION['_errors'] ?? []); return $e; }
function f_err(string $name): string { $e = $_SESSION['_errors'][$name] ?? null; return $e ? '<p class="f-error" id="err-' . e($name) . '">' . e(is_string($e) ? $e : 'Required') . '</p>' : ''; }
function f_attrs(array $o, string $name): string {
    $a = '';
    foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'pattern', 'autocomplete', 'inputmode', 'rows'] as $k) if (isset($o[$k])) $a .= " $k=\"" . e($o[$k]) . '"';
    foreach (['required', 'readonly', 'disabled', 'autofocus'] as $k) if (!empty($o[$k])) $a .= " $k";
    if (isset($_SESSION['_errors'][$name])) $a .= ' aria-invalid="true" aria-describedby="err-' . e($name) . '"';
    elseif (!empty($o['help'])) $a .= ' aria-describedby="h-' . e(slugify($name)) . '"';
    foreach (arr($o['data'] ?? []) as $k => $v) $a .= ' data-' . e($k) . '="' . e($v) . '"';
    return $a;
}
function f_id(string $name): string { return 'f-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'); }
function f_wrap(string $name, string $label, string $control, array $o): string {
    $help = !empty($o['help']) ? '<p class="f-help" id="h-' . e(slugify($name)) . '">' . $o['help'] . '</p>' : '';
    return '<div class="field' . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '"><label for="' . f_id($name) . '">' . e($label) . (!empty($o['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>' . $control . $help . f_err($name) . '</div>';
}
function f_text(string $name, string $label, mixed $value = '', array $o = []): string {
    $type = $o['type'] ?? 'text';
    $v = old($name, $value);
    return f_wrap($name, $label, '<input type="' . e($type) . '" id="' . f_id($name) . '" name="' . e($name) . '" value="' . e(is_array($v) ? '' : $v) . '"' . f_attrs($o, $name) . '>', $o);
}
function f_textarea(string $name, string $label, mixed $value = '', array $o = []): string {
    $o['rows'] ??= 4;
    return f_wrap($name, $label, '<textarea id="' . f_id($name) . '" name="' . e($name) . '"' . f_attrs($o, $name) . '>' . e(old($name, $value)) . '</textarea>', $o);
}
function f_select(string $name, string $label, array $options, mixed $value = '', array $o = []): string {
    $v = (string) old($name, $value);
    $h = '<select id="' . f_id($name) . '" name="' . e($name) . '"' . f_attrs($o, $name) . '>';
    if (isset($o['empty'])) $h .= '<option value="">' . e($o['empty']) . '</option>';
    foreach ($options as $k => $l) $h .= '<option value="' . e($k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e($l) . '</option>';
    return f_wrap($name, $label, $h . '</select>', $o);
}
function f_check(string $name, string $label, bool $checked = false, array $o = []): string {
    $c = isset($_SESSION['_old']) ? !empty($_SESSION['_old'][$name]) : $checked;
    return '<div class="field field-check"><label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($c ? ' checked' : '') . (!empty($o['disabled']) ? ' disabled' : '') . '> <span>' . e($label) . '</span></label>' . (!empty($o['help']) ? '<p class="f-help">' . $o['help'] . '</p>' : '') . '</div>';
}
function f_media(string $name, string $label, ?int $mediaId, int $shopId, array $o = []): string {
    $m = $mediaId ? media_by_id($mediaId, $shopId) : null;
    $prev = $m ? '<img src="' . e(media_url($m, 400)) . '" alt="">' : '<span class="muted">No image</span>';
    return '<div class="field media-field" data-media-field><span class="lbl">' . e($label) . '</span><div class="media-pick"><span class="media-prev" data-media-prev>' . $prev . '</span>'
        . '<input type="hidden" name="' . e($name) . '" value="' . (int) $mediaId . '" data-media-input>'
        . '<button type="button" class="btn btn-sm btn-ghost" data-media-open>Choose…</button> <button type="button" class="btn btn-sm btn-link" data-media-clear>Remove</button></div>' . (!empty($o['help']) ? '<p class="f-help">' . $o['help'] . '</p>' : '') . '</div>';
}
function confirm_attr(string $msg): string { return ' data-confirm="' . e($msg) . '"'; }

/** POST handler wrapper: catches validation/user errors, keeps input, flashes, PRG redirect. */
function handle(callable $fn, string $backTo): never {
    try {
        $r = $fn();
        clear_old(); unset($_SESSION['_errors']);
        redirect(is_string($r) && str_starts_with($r, '/') ? $r : $backTo);
    } catch (ValidationError $e) {
        remember_input(); $_SESSION['_errors'] = $e->errors;
        flash('error', 'Please correct the highlighted fields.');
    } catch (UserError $e) {
        remember_input();
        flash('error', $e->getMessage());
    }
    redirect($backTo);
}
function render_admin_like(string $layout, string $view, array $vars): never {
    security_headers(true);
    $content = view($view, $vars);
    unset($_SESSION['_errors']); clear_old();
    header('Content-Type: text/html; charset=utf-8');
    echo view($layout, ['content' => $content] + $vars);
    exit;
}
function sort_link(string $col, string $label): string {
    $cur = get('sort'); $dir = get('dir', 'desc');
    $nd = ($cur === $col && $dir === 'asc') ? 'desc' : 'asc';
    $ind = $cur === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . e(query_string(['sort' => $col, 'dir' => $nd, 'page' => null])) . '">' . e($label) . $ind . '</a>';
}
function quota_bar(array $q, bool $big = false): string {
    $cls = $q['active'] >= $q['limit'] ? 'bad' : ($q['pct'] >= 85 ? 'warn' : 'ok');
    return '<div class="quota' . ($big ? ' quota-big' : '') . ' quota-' . $cls . '" role="group" aria-label="Active products quota"><div class="quota-top"><span class="quota-l">Active products</span><strong>' . $q['active'] . ' / ' . $q['limit'] . '</strong></div>'
        . '<div class="quota-track" role="progressbar" aria-valuemin="0" aria-valuemax="' . $q['limit'] . '" aria-valuenow="' . $q['active'] . '"><span style="width:' . $q['pct'] . '%"></span></div>'
        . ($big ? '<div class="quota-sub"><span>' . number_format($q['inactive']) . ' inactive</span><span>' . number_format($q['stored']) . ' / ' . number_format($q['stored_limit']) . ' stored</span></div>' : '') . '</div>';
}
