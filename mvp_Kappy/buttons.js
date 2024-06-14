document.addEventListener('DOMContentLoaded', (event) => {
    const openPopupBtn = document.getElementById('openPopupBtn');
    const popup = document.getElementById('popup');
    const closePopupBtn = document.getElementById('closePopupBtn');

    openPopupBtn.addEventListener('click', () => {
        popup.style.display = 'flex'; // Show the popup
    });

    closePopupBtn.addEventListener('click', () => {
        popup.style.display = 'none'; // Hide the popup
    });

    window.addEventListener('click', (event) => {
        if (event.target === popup) {
            popup.style.display = 'none'; // Hide the popup if the user clicks outside of it
        }
    });
});