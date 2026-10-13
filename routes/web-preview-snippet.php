// Add this route to routes/web.php for local UI review. It is intentionally
// restricted to the local environment and must not be exposed in production.
if (app()->environment('local')) {
    Route::view('/ui-preview', 'ui.preview')->name('ui.preview');
}
