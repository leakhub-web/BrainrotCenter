// Filtres + recherche
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('#search');
    const filters = document.querySelectorAll('.filter-select');
    
    function applyFilters() {
        const annonces = document.querySelectorAll('.annonce-card');
        const term = searchInput.value.toLowerCase();
        const cat = document.querySelector('#cat-filter')?.value;
        const priceMin = document.querySelector('#price-min')?.value || 0;
        const priceMax = document.querySelector('#price-max')?.value || 999999;
        
        annonces.forEach(card => {
            const title = card.querySelector('.annonce-title').textContent.toLowerCase();
            const prix = parseFloat(card.dataset.prix);
            const category = card.dataset.category;
            
            let show = title.includes(term) &&
                       prix >= priceMin && prix <= priceMax &&
                       (!cat || category === cat);
            
            card.style.display = show ? 'block' : 'none';
        });
    }
    
    searchInput?.addEventListener('input', applyFilters);
    filters.forEach(f => f?.addEventListener('change', applyFilters));
    
    // Modals
    document.querySelectorAll('.open-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById(btn.dataset.modal).classList.add('active');
        });
    });
    
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelector('.modal.active')?.classList.remove('active');
        });
    });
    
    // Upload multiple images preview
    document.querySelector('#images')?.addEventListener('change', (e) => {
        const preview = document.querySelector('#image-preview');
        preview.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.width = '100px'; img.style.height = '100px'; img.style.objectFit = 'cover';
            preview.appendChild(img);
        });
    });
    
    // Fetch API pour messages/contact
    async function sendMessage(annonceId, message) {
        const res = await fetch('api.php?action=message', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({annonce_id: annonceId, message})
        });
        if (res.ok) alert('Message envoyé !');
    }
});
