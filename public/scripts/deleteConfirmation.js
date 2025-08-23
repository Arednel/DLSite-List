// Delete confirmation script
let deleteForm;

function openDeleteModal(event) {
    event.preventDefault();
    deleteForm = event.target.closest('form');
    document.getElementById('deleteModal').style.display = 'block';
    return false;
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function confirmDeletion() {
    if (deleteForm) {
        deleteForm.submit();
    }
}

// Optional: Close modal on ESC or outside click
window.onclick = function (event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeModal();
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === "Escape") {
        closeModal();
    }
});