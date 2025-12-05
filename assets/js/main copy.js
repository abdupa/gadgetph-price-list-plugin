document.addEventListener('DOMContentLoaded', function() {
    const PHP_TO_USD_RATE = 58.5;

    // --- THIS IS THE KEY CHANGE ---
    // Instead of the mock data array, we now use the 'priceListData' object
    // that WordPress has passed to us from our PHP code.
    const phoneData = priceListData.phones || [];

    // For now, we will keep the mock data for these sections. We can make them dynamic later.
    const comparisonData = [
        // { phoneA_id: 2, phoneB_id: 1, link: '#', key_feature: 'Camera' },
        // Add some default comparisons if needed
    ];
    const faqData = [
        { q: "Why are phone prices so different between retailers?", a: "Prices vary due to retailer overheads, stock levels, promotions, and whether the device is factory-unlocked or carrier-locked. Always check the model number and warranty details." },
        { q: "Which specs are most important for value?", a: "The best value often lies in the mid-range. Focus on the Processor (e.g., Snapdragon 7-series), a good AMOLED/OLED display, and at least 6GB of RAM. High megapixels matter less than sensor quality." },
        { q: "How often do smartphone prices drop after launch?", a: "Major price drops typically occur 6–12 months after launch, or immediately after a successor is announced. Budget devices see more gradual drops." }
    ];

    const initialLimit = 10;
    const displayStep = 10;
    let currentData = [...phoneData];
    let sortState = { key: 'price', direction: 'asc' };
    let currentIndex = initialLimit;

    const listDesktop = document.getElementById('phone-list-desktop');
    const listMobile = document.getElementById('phone-list-mobile');
    const noResultsMessage = document.getElementById('no-results');
    const loadMoreButtonContainer = document.getElementById('load-more-container');
    const brandFilter = document.getElementById('brand-filter');

    const formatPrice = (pricePhp, isPrimary = true) => {
        const priceUsd = Math.round(pricePhp / PHP_TO_USD_RATE);
        if (isPrimary) {
            return `<div>
                        <span class="text-lg font-bold text-green-600">₱${pricePhp.toLocaleString()}</span>
                        <span class="text-xs text-gray-500 font-normal ml-1">(~$${priceUsd.toLocaleString()})</span>
                    </div>`;
        }
        return `<div>
                    <span class="font-bold text-gray-800">₱${pricePhp.toLocaleString()}</span>
                    <span class="text-xs text-gray-500 font-normal ml-1">(~$${priceUsd.toLocaleString()})</span>
                </div>`;
    };
    
    const formatPriceMobile = (pricePhp) => {
        const priceUsd = Math.round(pricePhp / PHP_TO_USD_RATE);
        return `<div class="text-right ml-2 flex-shrink-0">
                    <p class="text-2xl font-extrabold text-green-600">₱${pricePhp.toLocaleString()}</p>
                    <p class="text-xs text-gray-500 font-normal">(~$${priceUsd.toLocaleString()})</p>
                </div>`;
    };

    function renderPhones(data) {
        const dataToDisplay = data.slice(0, currentIndex);
        if(listDesktop) listDesktop.innerHTML = dataToDisplay.map((phone, index) => renderDesktopRow(phone, index)).join('');
        if(listMobile) listMobile.innerHTML = dataToDisplay.map((phone, index) => renderMobileCard(phone, index)).join('');
        if(noResultsMessage) noResultsMessage.classList.toggle('hidden', data.length > 0);
        updateLoadMoreButton(data.length);
    }

    const popularBadge = `<span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded-full">⭐ Popular</span>`;
    
    function renderDesktopRow(phone, index) {
        return `
            <tr class="hover:bg-gray-50 transition duration-150">
                <td class="px-3 py-4 whitespace-nowrap text-sm font-semibold text-indigo-600">${index + 1}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <img src="${phone.imageUrl || 'https://placehold.co/80x80'}" alt="${phone.name}" class="h-12 w-12 rounded-lg object-cover mr-4 border border-gray-100 shadow-sm flex-shrink-0">
                        <div>
                            <a href="${phone.productUrl}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">${phone.name}</a>
                            ${phone.isPopular ? popularBadge : ''}
                            <p class="text-xs text-gray-500">${phone.brand}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${phone.ram} / ${phone.storage}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${phone.camera} / ${phone.display}</td>
                <td class="px-6 py-4 whitespace-nowrap">${formatPrice(phone.price, true)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                    <a href="${phone.productUrl}" class="inline-block bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-2 px-4 rounded-full transition duration-150">View Deal</a>
                </td>
            </tr>
        `;
    }

    function renderMobileCard(phone, index) {
        // This logic will need to be added if you want segment colors
        const segmentColor = 'green'; 
        return `
            <div class="bg-white p-4 rounded-xl card-shadow border-l-4 border-${segmentColor}-500">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-start flex-1">
                        <span class="text-xl font-extrabold text-indigo-600 mr-3 mt-1">${index + 1}.</span> 
                        <img src="${phone.imageUrl || 'https://placehold.co/80x80'}" alt="${phone.name}" class="h-12 w-12 rounded-lg object-cover mr-3 border border-gray-100 shadow-sm flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 leading-tight">${phone.name} ${phone.isPopular ? '⭐' : ''}</h3>
                            <p class="text-sm text-gray-500">${phone.brand}</p>
                        </div>
                    </div>
                    ${formatPriceMobile(phone.price)}
                </div>
                <div class="space-y-1 pt-2">
                    <div class="mobile-card-spec"><span class="font-medium text-gray-700">Storage/RAM:</span><span class="text-gray-800 font-semibold">${phone.ram} / ${phone.storage}</span></div>
                    <div class="mobile-card-spec"><span class="font-medium text-gray-700">Camera:</span><span class="text-gray-800 font-semibold">${phone.camera}</span></div>
                    <div class="mobile-card-spec"><span class="font-medium text-gray-700">Display:</span><span class="text-gray-800 font-semibold">${phone.display}</span></div>
                </div>
                <a href="${phone.productUrl}" class="mt-4 block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg transition duration-150">View Deal</a>
            </div>
        `;
    }
    
    function updateSortIcons() {
        document.querySelectorAll('.sort-icon-container').forEach(container => {
            const key = container.getAttribute('data-key');
            container.classList.toggle('opacity-100', key === sortState.key);
            container.classList.toggle('opacity-30', key !== sortState.key);
            const upArrow = container.querySelector('.up-arrow');
            const downArrow = container.querySelector('.down-arrow');
            upArrow.classList.toggle('active', key === sortState.key && sortState.direction === 'asc');
            downArrow.classList.toggle('active', key === sortState.key && sortState.direction === 'desc');
        });
    }
    
    window.showNextPhones = function() {
        currentIndex += displayStep;
        renderPhones(currentData);
    }

    window.showAllPhones = function() {
        currentIndex = currentData.length;
        renderPhones(currentData);
    }

    function updateLoadMoreButton(totalItems) {
        if (!loadMoreButtonContainer) return;
        const remaining = totalItems - currentIndex;
        if (remaining <= 0) {
            loadMoreButtonContainer.innerHTML = '';
        } else {
            const itemsToLoad = Math.min(displayStep, remaining);
            loadMoreButtonContainer.innerHTML = `
                <div class="flex flex-col items-center space-y-3">
                    <button onclick="showNextPhones()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-200">
                        Show next ${itemsToLoad} phones (${currentIndex}/${totalItems} shown)
                    </button>
                    <a href="javascript:void(0)" onclick="showAllPhones()" class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold underline">
                        Or show all ${totalItems} phones
                    </a>
                </div>`;
        }
    }

    function filterPhones() {
        const searchText = document.getElementById('search-input').value.toLowerCase();
        const maxPrice = document.getElementById('price-filter').value;
        const selectedBrand = document.getElementById('brand-filter').value;

        currentData = phoneData.filter(phone => {
            const searchString = `${phone.name} ${phone.brand} ${phone.storage} ${phone.ram} ${phone.camera} ${phone.display}`.toLowerCase();
            const searchMatch = !searchText || searchString.includes(searchText);
            const priceMatch = maxPrice === 'all' || phone.price <= parseInt(maxPrice);
            const brandMatch = selectedBrand === 'all' || phone.brand === selectedBrand;
            return searchMatch && priceMatch && brandMatch;
        });
        
        currentIndex = initialLimit;
        sortAndRender();
    }

    window.sortPhones = function(key) {
        if (sortState.key === key) {
            sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortState.key = key;
            sortState.direction = 'asc';
        }
        sortAndRender();
    }
    
    function sortAndRender() {
        const { key, direction } = sortState;
        currentData.sort((a, b) => {
            const valA = a[key];
            const valB = b[key];
            let comparison = 0;
            if (typeof valA === 'string') {
                comparison = valA.localeCompare(valB);
            } else {
                comparison = valA - valB;
            }
            return direction === 'asc' ? comparison : comparison * -1;
        });
        updateSortIcons();
        renderPhones(currentData);
    }

    function setDynamicDates() {
        const now = new Date();
        const month = now.toLocaleString('default', { month: 'long' });
        const year = now.getFullYear();
        const monthYearSpan = document.getElementById('current-month-year');
        if (monthYearSpan) {
            monthYearSpan.textContent = `${month}, ${year}`;
        }
    }
    
    function populateBrandFilter() {
        if (!brandFilter) return;
        const brands = [...new Set(phoneData.map(p => p.brand))].sort();
        brands.forEach(brand => {
            const option = document.createElement('option');
            option.value = brand;
            option.textContent = brand;
            brandFilter.appendChild(option);
        });
    }

    // --- INITIALIZATION ---
    setDynamicDates();
    populateBrandFilter();
    sortAndRender(); // Initial render
    
    // Add event listeners
    const searchInput = document.getElementById('search-input');
    const priceFilter = document.getElementById('price-filter');
    
    if(searchInput) searchInput.addEventListener('keyup', filterPhones);
    if(brandFilter) brandFilter.addEventListener('change', filterPhones);
    if(priceFilter) priceFilter.addEventListener('change', filterPhones);
});