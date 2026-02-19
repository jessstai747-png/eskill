/**
 * Command Palette Logic
 * Handles opening, closing, filtering, and navigation of the command palette.
 */
document.addEventListener('DOMContentLoaded', function () {
    const palette = document.getElementById('command-palette');
    const input = document.getElementById('cp-input');
    const resultsContainer = document.getElementById('cp-results');
    const items = document.querySelectorAll('.cp-item');
    const emptyState = document.getElementById('cp-empty');

    let isOpen = false;
    let activeIndex = 0;

    // Toggle Palette
    function togglePalette() {
        isOpen = !isOpen;
        palette.style.display = isOpen ? 'flex' : 'none';

        if (isOpen) {
            input.value = '';
            filterItems('');
            input.focus();
            setActiveItem(0);
        }
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', function (e) {
        // Cmd+K or Ctrl+K to toggle
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            togglePalette();
        }

        if (!isOpen) return;

        // Navigation
        if (e.key === 'Escape') {
            togglePalette();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigate(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigate(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            selectActiveItem();
        }
    });

    // Close on backdrop click
    palette.addEventListener('click', function (e) {
        if (e.target === palette) {
            togglePalette();
        }
    });

    // Filtering
    input.addEventListener('input', function (e) {
        filterItems(e.target.value.toLowerCase());
    });

    function filterItems(query) {
        let visibleCount = 0;
        let firstVisibleIndex = -1;

        items.forEach((item, index) => {
            const keywords = item.dataset.keywords || '';
            const text = item.innerText.toLowerCase();
            const match = text.includes(query) || keywords.includes(query);

            if (match) {
                item.style.display = 'flex';
                visibleCount++;
                if (firstVisibleIndex === -1) firstVisibleIndex = index;
            } else {
                item.style.display = 'none';
            }
        });

        emptyState.style.display = visibleCount === 0 ? 'flex' : 'none';

        // Reset selection to first visible item
        if (visibleCount > 0) {
            setActiveItem(firstVisibleIndex);
        }
    }

    function navigate(direction) {
        const visibleItems = Array.from(items).filter(item => item.style.display !== 'none');
        if (visibleItems.length === 0) return;

        let currentVisibleIndex = visibleItems.indexOf(items[activeIndex]);

        // If current active is not visible (due to filter), start from 0
        if (currentVisibleIndex === -1) currentVisibleIndex = 0;

        let newVisibleIndex = currentVisibleIndex + direction;

        if (newVisibleIndex >= visibleItems.length) newVisibleIndex = 0;
        if (newVisibleIndex < 0) newVisibleIndex = visibleItems.length - 1;

        // Find the index in the original items array
        const newItem = visibleItems[newVisibleIndex];
        const newIndex = Array.from(items).indexOf(newItem);

        setActiveItem(newIndex);
    }

    function setActiveItem(index) {
        items[activeIndex]?.classList.remove('active');
        if (index >= 0 && index < items.length) {
            activeIndex = index;
            items[activeIndex].classList.add('active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function selectActiveItem() {
        const item = items[activeIndex];
        if (item && item.style.display !== 'none') {
            item.click();
        }
    }

    // Expose toggle globally for button clicks
    window.openCommandPalette = function () {
        if (!isOpen) togglePalette();
    };
});
