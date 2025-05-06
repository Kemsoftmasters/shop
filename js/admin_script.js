// Handle delete confirmation for Category links
const deleteCategoryLinks = document.querySelectorAll('.delete-category-link');

deleteCategoryLinks.forEach(link => {
    link.addEventListener('click', function(event) {
        event.preventDefault();
        const deleteUrl = this.href;
        const confirmDelete = confirm('Are you sure you want to delete this category? Products assigned to this category might be affected.'); // Provide a warning

        if (confirmDelete) {
            window.location.href = deleteUrl;
        }
    });
});

// Keep your existing delete-product-link handling below this
// ...

document.addEventListener('DOMContentLoaded', function() {
    // Find all elements with the class 'delete-product-link'
    const deleteLinks = document.querySelectorAll('.delete-product-link');

    // Add a click event listener to each link
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            // Prevent the default link behavior (navigating directly)
            event.preventDefault();

            // Get the URL from the link's href attribute
            const deleteUrl = this.href;

            // Show a confirmation dialog
            const confirmDelete = confirm('Are you sure you want to delete this product? This action cannot be undone.');

            // If the user confirms, navigate to the delete URL
            if (confirmDelete) {
                window.location.href = deleteUrl;
            }
            // If the user cancels, do nothing (default prevented already)
        });
    });

    // You can add other JavaScript functionalities here for other parts of the admin panel
    // ...
});
