<?php
declare(strict_types=1);

/** Storefront strings. Admin UI is English; storefront supports Greek (el) and English (en). */
function t(string $key, array $vars = []): string {
    static $dict = null;
    $dict ??= [
        'el' => [
            'home' => 'Αρχική', 'shop' => 'Κατάστημα', 'all_products' => 'Όλα τα προϊόντα', 'categories' => 'Κατηγορίες', 'search' => 'Αναζήτηση',
            'search_placeholder' => 'Αναζήτηση προϊόντων…', 'cart' => 'Καλάθι', 'wishlist' => 'Αγαπημένα', 'account' => 'Λογαριασμός', 'menu' => 'Μενού', 'close' => 'Κλείσιμο',
            'add_to_cart' => 'Προσθήκη στο καλάθι', 'added_to_cart' => 'Προστέθηκε στο καλάθι', 'view_cart' => 'Προβολή καλαθιού', 'checkout' => 'Ολοκλήρωση αγοράς',
            'continue_shopping' => 'Συνέχεια αγορών', 'quantity' => 'Ποσότητα', 'remove' => 'Αφαίρεση', 'subtotal' => 'Υποσύνολο', 'shipping' => 'Μεταφορικά', 'total' => 'Σύνολο',
            'free' => 'Δωρεάν', 'in_stock' => 'Διαθέσιμο', 'out_of_stock' => 'Εξαντλημένο', 'low_stock' => 'Λίγα τεμάχια', 'sku' => 'Κωδικός', 'brand' => 'Μάρκα',
            'description' => 'Περιγραφή', 'specifications' => 'Χαρακτηριστικά', 'faq' => 'Συχνές ερωτήσεις', 'related' => 'Σχετικά προϊόντα', 'sale' => 'Προσφορά', 'new' => 'Νέο',
            'choose_option' => 'Επιλέξτε', 'variant' => 'Επιλογή', 'sort' => 'Ταξινόμηση', 'sort_newest' => 'Νεότερα', 'sort_price_asc' => 'Τιμή: χαμηλή → υψηλή',
            'sort_price_desc' => 'Τιμή: υψηλή → χαμηλή', 'sort_name' => 'Όνομα', 'filters' => 'Φίλτρα', 'apply' => 'Εφαρμογή', 'clear' => 'Καθαρισμός', 'price' => 'Τιμή',
            'min' => 'Από', 'max' => 'Έως', 'only_in_stock' => 'Μόνο διαθέσιμα', 'products_count' => '{n} προϊόντα', 'no_products' => 'Δεν βρέθηκαν προϊόντα.',
            'empty_cart' => 'Το καλάθι σας είναι άδειο.', 'empty_wishlist' => 'Δεν έχετε αγαπημένα προϊόντα ακόμα.', 'add_wishlist' => 'Προσθήκη στα αγαπημένα', 'remove_wishlist' => 'Αφαίρεση από τα αγαπημένα',
            'contact' => 'Επικοινωνία', 'name' => 'Ονοματεπώνυμο', 'email' => 'Email', 'phone' => 'Τηλέφωνο', 'message' => 'Μήνυμα', 'send' => 'Αποστολή',
            'consent_privacy' => 'Συμφωνώ με την επεξεργασία των στοιχείων μου σύμφωνα με την Πολιτική Απορρήτου.', 'message_sent' => 'Ευχαριστούμε! Το μήνυμά σας στάλθηκε.',
            'address' => 'Διεύθυνση', 'city' => 'Πόλη', 'postcode' => 'Τ.Κ.', 'country' => 'Χώρα', 'notes' => 'Σημειώσεις παραγγελίας', 'contact_details' => 'Στοιχεία επικοινωνίας',
            'shipping_address' => 'Διεύθυνση αποστολής', 'shipping_method' => 'Τρόπος αποστολής', 'payment_method' => 'Τρόπος πληρωμής', 'order_summary' => 'Σύνοψη παραγγελίας',
            'accept_terms' => 'Έχω διαβάσει και αποδέχομαι τους Όρους Χρήσης και την Πολιτική Απορρήτου.', 'place_order' => 'Ολοκλήρωση παραγγελίας',
            'order_received' => 'Η παραγγελία σας καταχωρήθηκε!', 'order_number' => 'Αριθμός παραγγελίας', 'order_email_note' => 'Θα λάβετε επιβεβαίωση στο {email}.',
            'login' => 'Σύνδεση', 'logout' => 'Αποσύνδεση', 'register' => 'Εγγραφή', 'password' => 'Κωδικός', 'forgot_password' => 'Ξεχάσατε τον κωδικό;', 'my_orders' => 'Οι παραγγελίες μου',
            'order' => 'Παραγγελία', 'date' => 'Ημερομηνία', 'status' => 'Κατάσταση', 'view' => 'Προβολή', 'newsletter' => 'Newsletter', 'subscribe' => 'Εγγραφή',
            'subscribed' => 'Ευχαριστούμε για την εγγραφή σας!', 'unsubscribed' => 'Η διεύθυνσή σας αφαιρέθηκε από τη λίστα.', 'page_not_found' => 'Η σελίδα δεν βρέθηκε',
            'page_not_found_text' => 'Η σελίδα που ζητήσατε δεν υπάρχει ή έχει μετακινηθεί.', 'back_home' => 'Επιστροφή στην αρχική', 'gone' => 'Η σελίδα δεν είναι πλέον διαθέσιμη',
            'shop_by_category' => 'Αγορά ανά κατηγορία', 'featured' => 'Επιλεγμένα προϊόντα', 'view_all' => 'Δείτε όλα', 'results_for' => 'Αποτελέσματα για «{q}»',
            'cookie_text' => 'Χρησιμοποιούμε απαραίτητα cookies για τη λειτουργία του καταστήματος και, με τη συγκατάθεσή σας, cookies στατιστικών.', 'accept' => 'Αποδοχή', 'decline' => 'Απόρριψη',
            'lock_title' => 'Το ηλεκτρονικό κατάστημα είναι προσωρινά μη διαθέσιμο.', 'lock_text' => 'Θα είμαστε ξανά κοντά σας σύντομα.',
            'skip' => 'Μετάβαση στο περιεχόμενο', 'breadcrumb' => 'Διαδρομή', 'hours' => 'Ωράριο', 'follow' => 'Ακολουθήστε μας', 'info' => 'Πληροφορίες', 'help' => 'Εξυπηρέτηση',
            'out_of_stock_cart' => 'Κάποια προϊόντα δεν είναι πλέον διαθέσιμα και αφαιρέθηκαν από το καλάθι.', 'required' => 'υποχρεωτικό', 'fix_errors' => 'Παρακαλούμε διορθώστε τα πεδία με σφάλμα.',
            'quick_add' => 'Γρήγορη προσθήκη', 'details' => 'Λεπτομέρειες', 'payment_status' => 'Πληρωμή', 'items' => 'Προϊόντα', 'update' => 'Ενημέρωση', 'reset_password' => 'Επαναφορά κωδικού',
            'reset_sent' => 'Αν υπάρχει λογαριασμός με αυτό το email, θα λάβετε σύνδεσμο επαναφοράς.', 'new_password' => 'Νέος κωδικός', 'saved' => 'Αποθηκεύτηκε.',
            'invalid_login' => 'Λάθος email ή κωδικός.', 'account_exists' => 'Υπάρχει ήδη λογαριασμός με αυτό το email.', 'welcome' => 'Καλώς ήρθατε', 'compare_at' => 'Αρχική τιμή',
            'secure_checkout' => 'Ασφαλής ολοκλήρωση αγοράς', 'bank_details' => 'Στοιχεία τραπεζικής κατάθεσης', 'tax_included' => 'Οι τιμές περιλαμβάνουν ΦΠΑ.',
        ],
        'en' => [
            'home' => 'Home', 'shop' => 'Shop', 'all_products' => 'All products', 'categories' => 'Categories', 'search' => 'Search',
            'search_placeholder' => 'Search products…', 'cart' => 'Cart', 'wishlist' => 'Wishlist', 'account' => 'Account', 'menu' => 'Menu', 'close' => 'Close',
            'add_to_cart' => 'Add to cart', 'added_to_cart' => 'Added to cart', 'view_cart' => 'View cart', 'checkout' => 'Checkout',
            'continue_shopping' => 'Continue shopping', 'quantity' => 'Quantity', 'remove' => 'Remove', 'subtotal' => 'Subtotal', 'shipping' => 'Shipping', 'total' => 'Total',
            'free' => 'Free', 'in_stock' => 'In stock', 'out_of_stock' => 'Out of stock', 'low_stock' => 'Only a few left', 'sku' => 'SKU', 'brand' => 'Brand',
            'description' => 'Description', 'specifications' => 'Specifications', 'faq' => 'FAQ', 'related' => 'Related products', 'sale' => 'Sale', 'new' => 'New',
            'choose_option' => 'Choose', 'variant' => 'Option', 'sort' => 'Sort', 'sort_newest' => 'Newest', 'sort_price_asc' => 'Price: low → high',
            'sort_price_desc' => 'Price: high → low', 'sort_name' => 'Name', 'filters' => 'Filters', 'apply' => 'Apply', 'clear' => 'Clear', 'price' => 'Price',
            'min' => 'Min', 'max' => 'Max', 'only_in_stock' => 'In stock only', 'products_count' => '{n} products', 'no_products' => 'No products found.',
            'empty_cart' => 'Your cart is empty.', 'empty_wishlist' => 'Your wishlist is empty.', 'add_wishlist' => 'Add to wishlist', 'remove_wishlist' => 'Remove from wishlist',
            'contact' => 'Contact', 'name' => 'Full name', 'email' => 'Email', 'phone' => 'Phone', 'message' => 'Message', 'send' => 'Send',
            'consent_privacy' => 'I agree to the processing of my data according to the Privacy Policy.', 'message_sent' => 'Thank you! Your message has been sent.',
            'address' => 'Address', 'city' => 'City', 'postcode' => 'Postcode', 'country' => 'Country', 'notes' => 'Order notes', 'contact_details' => 'Contact details',
            'shipping_address' => 'Shipping address', 'shipping_method' => 'Shipping method', 'payment_method' => 'Payment method', 'order_summary' => 'Order summary',
            'accept_terms' => 'I have read and accept the Terms and the Privacy Policy.', 'place_order' => 'Place order',
            'order_received' => 'Your order has been received!', 'order_number' => 'Order number', 'order_email_note' => 'A confirmation will be sent to {email}.',
            'login' => 'Sign in', 'logout' => 'Sign out', 'register' => 'Create account', 'password' => 'Password', 'forgot_password' => 'Forgot your password?', 'my_orders' => 'My orders',
            'order' => 'Order', 'date' => 'Date', 'status' => 'Status', 'view' => 'View', 'newsletter' => 'Newsletter', 'subscribe' => 'Subscribe',
            'subscribed' => 'Thanks for subscribing!', 'unsubscribed' => 'You have been unsubscribed.', 'page_not_found' => 'Page not found',
            'page_not_found_text' => 'The page you requested does not exist or has moved.', 'back_home' => 'Back to home', 'gone' => 'This page is no longer available',
            'shop_by_category' => 'Shop by category', 'featured' => 'Featured products', 'view_all' => 'View all', 'results_for' => 'Results for “{q}”',
            'cookie_text' => 'We use essential cookies to run the store and, with your consent, analytics cookies.', 'accept' => 'Accept', 'decline' => 'Decline',
            'lock_title' => 'This online store is temporarily unavailable.', 'lock_text' => 'We will be back with you shortly.',
            'skip' => 'Skip to content', 'breadcrumb' => 'Breadcrumb', 'hours' => 'Opening hours', 'follow' => 'Follow us', 'info' => 'Information', 'help' => 'Customer care',
            'out_of_stock_cart' => 'Some products are no longer available and were removed from your cart.', 'required' => 'required', 'fix_errors' => 'Please correct the highlighted fields.',
            'quick_add' => 'Quick add', 'details' => 'Details', 'payment_status' => 'Payment', 'items' => 'Items', 'update' => 'Update', 'reset_password' => 'Reset password',
            'reset_sent' => 'If an account exists for that email, a reset link is on its way.', 'new_password' => 'New password', 'saved' => 'Saved.',
            'invalid_login' => 'Invalid email or password.', 'account_exists' => 'An account with this email already exists.', 'welcome' => 'Welcome', 'compare_at' => 'Was',
            'secure_checkout' => 'Secure checkout', 'bank_details' => 'Bank transfer details', 'tax_included' => 'Prices include VAT.',
        ],
    ];
    $loc = $GLOBALS['LOCALE'] ?? 'el';
    $s = $dict[$loc][$key] ?? $dict['en'][$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string) $v, $s);
    return $s;
}
