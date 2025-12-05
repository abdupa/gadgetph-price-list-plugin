document.addEventListener('DOMContentLoaded', function() {
    console.log("Smartphone Price List Script v1.3 -- Space-Insensitive Search -- is running.");

    const phoneData = (priceListData.phones || []).map(phone => {
        phone.price = parseFloat(phone.price) || 0;
        return phone;
    });

    const initialLimit = 10;
    const displayStep = 10;
    let currentData = [...phoneData];
    let currentIndex = initialLimit;
    let sortState = { key: 'price', direction: 'asc' };

    const phoneListContainer = document.getElementById('phone-list-container');
    const noResultsMessage = document.getElementById('no-results');
    const loadMoreButtonContainer = document.getElementById('load-more-container');
    const brandFilter = document.getElementById('brand-filter');

    const formatPrice = (pricePhp, regularPricePhp, isPrimary = true) => {
        const salePrice = `₱${pricePhp.toLocaleString()}`;
        if (regularPricePhp && regularPricePhp > pricePhp) {
            const originalPrice = `₱${regularPricePhp.toLocaleString()}`;
            if (isPrimary) {
                return `<div class="text-right"><del class="text-base font-normal text-gray-400">${originalPrice}</del><p class="text-2xl font-extrabold text-red-600">${salePrice}</p></div>`;
            } else {
                return `<div class="text-right leading-tight"><del class="text-xs font-normal text-gray-400">${originalPrice}</del><p class="font-bold text-red-600">${salePrice}</p></div>`;
            }
        }
        if (isPrimary) {
            return `<div class="text-right"><p class="text-2xl font-extrabold text-green-600">${salePrice}</p></div>`;
        } else {
            return `<p class="font-bold text-gray-800">${salePrice}</p>`;
        }
    };

    function renderPhoneRow(phone, index) {
        const specItems = [];

        if (phone.processor && phone.processor !== 'N/A') specItems.push(`<li><strong>Processor:</strong> <span class="text-gray-900">${phone.processor}</span></li>`);
        
        const hasRam = phone.ram && phone.ram !== 'N/A';
        const hasStorage = phone.storage && phone.storage !== 'N/A';
        if (hasRam || hasStorage) {
            const ramStorageText = [hasRam ? phone.ram : null, hasStorage ? phone.storage : null].filter(Boolean).join(' / ');
            specItems.push(`<li><strong>RAM/Storage:</strong> <span class="text-gray-900">${ramStorageText}</span></li>`);
        }

        if (phone.display && phone.display !== 'N/A') specItems.push(`<li><strong>Display:</strong> <span class="text-gray-900">${phone.display}</span></li>`);
        if (phone.camera && phone.camera !== 'N/A') specItems.push(`<li><strong>Camera:</strong> <span class="text-gray-900">${phone.camera} Main Camera</span></li>`);
        if (phone.battery && phone.battery !== 'N/A') specItems.push(`<li><strong>Battery:</strong> <span class="text-gray-900">${phone.battery}</span></li>`);
        
        const specListHtml = specItems.join('');

        return `
            <div class="bg-white p-4 rounded-xl card-shadow flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex-shrink-0 md:w-1/6 text-center relative">
                    <figure class="relative inline-block">
                        <span class="rank-count">${index + 1}</span>
                        <a href="${phone.productUrl}"><img src="${phone.imageUrl || 'https://placehold.co/128x128'}" alt="${phone.name}" class="h-24 w-24 md:h-32 md:w-32 rounded-lg object-cover border shadow-sm"></a>
                    </figure>
                </div>
                <div class="flex-grow md:w-3/6">
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2"><a href="${phone.productUrl}" class="hover:text-indigo-600">${phone.name}</a></h3>
                    <ul class="text-sm text-gray-700 spec-list">
                        ${specListHtml}
                    </ul>
                </div>
                <div class="flex-shrink-0 md:w-2/6 md:text-center md:border-l md:pl-6">
                    <div class="flex md:flex-col items-center justify-between">
                        ${formatPrice(phone.price, phone.regular_price)}
                        <div class="mt-0 md:mt-4 flex flex-col items-stretch w-1/2 md:w-full">
                            <a href="${phone.dealUrl}" target="_blank" rel="noopener noreferrer" class="inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-5 rounded-lg transition text-center">View Deal</a>
                            <a href="${phone.productUrl}" class="text-sm text-indigo-600 hover:text-indigo-800 mt-2 text-center">View Full Specs</a>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    function renderPhones(data) { 
        const dataToDisplay = data.slice(0, currentIndex); 
        if(phoneListContainer) phoneListContainer.innerHTML = dataToDisplay.map((phone, index) => renderPhoneRow(phone, index)).join(''); 
        if(noResultsMessage) noResultsMessage.classList.toggle('hidden', data.length > 0); 
        updateLoadMoreButton(data.length); 
    }

    function sortAndRender() { 
        const { key, direction } = sortState; 
        currentData.sort((a, b) => { 
            const valA = a[key]; 
            const valB = b[key]; 
            let comparison = 0; 
            if (typeof valA === 'string') { comparison = valA.localeCompare(valB); } 
            else { comparison = valA - valB; } 
            return direction === 'asc' ? comparison : comparison * -1; 
        }); 
        renderPhones(currentData); 
    }

    function updateLoadMoreButton(totalItems) { 
        if (!loadMoreButtonContainer) return; 
        const remaining = totalItems - currentIndex; 
        if (remaining <= 0 || currentIndex >= totalItems) { 
            loadMoreButtonContainer.innerHTML = ''; 
        } else { 
            const itemsToLoad = Math.min(displayStep, remaining); 
            loadMoreButtonContainer.innerHTML = ` <div class="flex flex-col items-center space-y-3"> <button onclick="window.showNextPhones()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-full shadow-lg"> Show next ${itemsToLoad} phones (${currentIndex}/${totalItems} shown) </button> <a href="javascript:void(0)" onclick="window.showAllPhones()" class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold underline"> Or show all ${totalItems} phones </a> </div>`; 
        } 
    }
    
    window.showNextPhones = function() { currentIndex += displayStep; renderPhones(currentData); }
    window.showAllPhones = function() { currentIndex = currentData.length; renderPhones(currentData); }

    function filterPhones() {
        // 1. Get the raw input
        const rawInput = document.getElementById('search-input').value.toLowerCase();
        
        // 2. Remove ALL spaces from input. 
        // "5000 mAh" becomes "5000mah". "5000mAh" becomes "5000mah".
        const tightSearchText = rawInput.replace(/\s+/g, '');

        const maxPrice = document.getElementById('price-filter').value;
        const selectedBrand = document.getElementById('brand-filter').value;

        currentData = phoneData.filter(phone => {
            // 3. Combine phone data into one string
            const rawDataString = [
                phone.name,
                phone.brand,
                phone.processor,
                phone.ram,
                phone.storage,
                phone.battery,
                phone.camera,
                phone.display
            ].filter(Boolean).join(' ').toLowerCase();

            // 4. Remove ALL spaces from the phone data as well
            const tightDataString = rawDataString.replace(/\s+/g, '');

            // 5. Check if the "tight" search exists in the "tight" data
            const searchMatch = !tightSearchText || tightDataString.includes(tightSearchText);
            
            let priceMatch = false;
            if (maxPrice === 'all') { priceMatch = true; } 
            else if (maxPrice === '10000') { priceMatch = phone.price <= 10000; } 
            else if (maxPrice === '25000') { priceMatch = phone.price > 10000 && phone.price <= 25000; } 
            else if (maxPrice === '50000') { priceMatch = phone.price > 25000 && phone.price <= 50000; } 
            else if (maxPrice === '150000') { priceMatch = phone.price > 50000; }
            
            const brandMatch = selectedBrand === 'all' || phone.brand === selectedBrand;

            return searchMatch && priceMatch && brandMatch;
        });

        currentIndex = initialLimit; 
        sortAndRender();
    }
    
    function populateBrandFilter() { 
        if (!brandFilter) return; 
        const brands = [...new Set(phoneData.map(p => p.brand))].sort(); 
        brands.forEach(brand => { 
            const option = document.createElement('option'); 
            option.value = brand; option.textContent = brand; 
            brandFilter.appendChild(option); 
        }); 
    }

    // function populateAllSections() { 
    //     const lastUpdated = document.getElementById('last-updated-date'); 
    //     if(lastUpdated) lastUpdated.textContent = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); 
        
    //     const renderContender = (phone) => `<li class="flex items-center justify-between py-3 border-t border-gray-200"><a href="${phone.productUrl}" class="hover:underline text-gray-800">${phone.name}</a> ${formatPrice(phone.price, phone.regular_price, false)}</li>`; 
        
    //     document.getElementById('budget-contenders').innerHTML = phoneData.filter(p=>p.price<=10000).sort((a,b)=>a.price-b.price).slice(0,3).map(renderContender).join('') || '<li>No phones.</li>'; 
    //     document.getElementById('mid-range-contenders').innerHTML = phoneData.filter(p=>p.price>10000&&p.price<=25000).sort((a,b)=>a.price-b.price).slice(0,3).map(renderContender).join('') || '<li>No phones.</li>'; 
    //     document.getElementById('premium-contenders').innerHTML = phoneData.filter(p=>p.price>25000&&p.price<=50000).sort((a,b)=>a.price-b.price).slice(0,3).map(renderContender).join('') || '<li>No phones.</li>'; 
    //     document.getElementById('flagship-contenders').innerHTML = phoneData.filter(p=>p.price>50000).sort((a,b)=>b.price-a.price).slice(0,3).map(renderContender).join('') || '<li>No phones.</li>'; 
        
    //     const popularPhones = phoneData.filter(p => p.isPopular).slice(0, 5); 
    //     const popularPicksContainer = document.getElementById('popular-picks-container'); 
    //     if(popularPicksContainer) { 
    //         const renderPopularPick = (phone) => `<div class="flex-shrink-0 w-64 bg-white rounded-xl card-shadow p-4"><div class="flex items-center mb-3"><img src="${phone.imageUrl}" alt="${phone.name}" class="h-10 w-10 rounded-lg object-cover mr-3 border"><div><a href="${phone.productUrl}" class="font-bold text-gray-800 hover:text-indigo-600 leading-tight text-sm">${phone.name}</a><p class="text-xs text-gray-500">${phone.brand}</p></div></div><div class="flex justify-between items-center">${formatPrice(phone.price, phone.regular_price, false)}<a href="${phone.productUrl}" class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-bold py-2 px-3 rounded-full transition">View</a></div></div>`; 
    //         popularPicksContainer.innerHTML = popularPhones.map(renderPopularPick).join(''); 
    //     } 
    // }

    function populateAllSections() { 
        const lastUpdated = document.getElementById('last-updated-date'); 
        
        // 1. UPDATED DATE LOGIC: Use Server Date (priceListData.lastUpdated)
        if(lastUpdated) {
            lastUpdated.textContent = priceListData.lastUpdated || new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); 
        }
        
        // 2. COMPACT RENDERER
        // Changes: py-3 -> py-1.5 | Added 'truncate' to handle long names cleanly
        const renderContender = (phone) => `
            <li class="flex items-center justify-between py-1.5 border-t border-gray-100">
                <a href="${phone.productUrl}" class="hover:underline text-gray-800 text-sm font-medium truncate w-2/3" title="${phone.name}">
                    ${phone.name}
                </a> 
                ${formatPrice(phone.price, phone.regular_price, false)}
            </li>`;
        
        // 3. UPDATED LISTS (Showing top 3 items)
        document.getElementById('budget-contenders').innerHTML = phoneData.filter(p=>p.price<=10000).sort((a,b)=>a.price-b.price).slice(0,3).map(renderContender).join('') || '<li class="py-2 text-xs text-gray-500">No phones found.</li>'; 
        document.getElementById('mid-range-contenders').innerHTML = phoneData.filter(p=>p.price>10000&&p.price<=25000).sort((a,b)=>a.price-b.price).slice(0,3).map(renderContender).join('') || '<li class="py-2 text-xs text-gray-500">No phones found.</li>'; 
        document.getElementById('premium-contenders').innerHTML = phoneData.filter(p=>p.price>25000&&p.price<=50000).sort((a,b)=>a.price-b.price).slice(0,3).map(renderContender).join('') || '<li class="py-2 text-xs text-gray-500">No phones found.</li>'; 
        document.getElementById('flagship-contenders').innerHTML = phoneData.filter(p=>p.price>50000).sort((a,b)=>b.price-a.price).slice(0,3).map(renderContender).join('') || '<li class="py-2 text-xs text-gray-500">No phones found.</li>'; 
        
        // Popular Picks (Unchanged)
        const popularPhones = phoneData.filter(p => p.isPopular).slice(0, 5); 
        const popularPicksContainer = document.getElementById('popular-picks-container'); 
        // if(popularPicksContainer) { 
        //     // const renderPopularPick = (phone) => `<div class="flex-shrink-0 w-64 bg-white rounded-xl card-shadow p-4"><div class="flex items-center mb-3"><img src="${phone.imageUrl}" alt="${phone.name}" class="h-10 w-10 rounded-lg object-cover mr-3 border"><div><a href="${phone.productUrl}" class="font-bold text-gray-800 hover:text-indigo-600 leading-tight text-sm">${phone.name}</a><p class="text-xs text-gray-500">${phone.brand}</p></div></div><div class="flex justify-between items-center">${formatPrice(phone.price, phone.regular_price, false)}<a href="${phone.productUrl}" class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-bold py-2 px-3 rounded-full transition">View</a></div></div>`; 
        //     const renderPopularPick = (phone) => `
        //         <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow h-full flex flex-col justify-between">
        //             <div>
        //                 <div class="flex items-center mb-3">
        //                     <img src="${phone.imageUrl}" alt="${phone.name}" class="h-12 w-12 rounded-lg object-cover mr-3 border border-gray-200 bg-white">
        //                     <div class="min-w-0">
        //                         <a href="${phone.productUrl}" class="block font-bold text-gray-900 hover:text-blue-600 leading-tight text-sm truncate" title="${phone.name}">
        //                             ${phone.name}
        //                         </a>
        //                         <p class="text-xs text-gray-500 truncate">${phone.brand}</p>
        //                     </div>
        //                 </div>
        //             </div>
        //             <div class="flex justify-between items-center mt-2 border-t border-gray-200 pt-3">
        //                 ${formatPrice(phone.price, phone.regular_price, false)}
        //                 <a href="${phone.productUrl}" class="text-xs font-bold text-blue-600 hover:text-blue-800 uppercase tracking-wide">
        //                     View &rarr;
        //                 </a>
        //             </div>
        //         </div>`;
        //     popularPicksContainer.innerHTML = popularPhones.map(renderPopularPick).join(''); 
        // } 
        if(popularPicksContainer) { 
            const renderPopularPick = (phone) => `
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow h-full flex flex-col justify-between">
        <div>
            <div class="flex items-start gap-3 mb-3">
                <img src="${phone.imageUrl}" alt="${phone.name}" class="flex-shrink-0 h-12 w-12 rounded-lg object-cover border border-gray-200 bg-white">
                <div class="min-w-0 flex-grow">
                    <a href="${phone.productUrl}" class="block font-bold text-gray-900 hover:text-blue-600 leading-tight text-sm min-h-[40px]" title="${phone.name}">
                        ${phone.name}
                    </a>
                    <p class="text-xs text-gray-500 truncate mt-1">${phone.brand}</p>
                </div>
            </div>
        </div>
        <div class="flex justify-between items-center mt-2 border-t border-gray-200 pt-3">
            ${formatPrice(phone.price, phone.regular_price, false)}
            <a href="${phone.productUrl}" class="text-xs font-bold text-blue-600 hover:text-blue-800 uppercase tracking-wide">
                View &rarr;
            </a>
        </div>
    </div>`;
            popularPicksContainer.innerHTML = popularPhones.map(renderPopularPick).join(''); 
        }
    }

    sortAndRender(); 
    populateBrandFilter();
    populateAllSections();
    
    document.getElementById('search-input').addEventListener('keyup', filterPhones);
    document.getElementById('brand-filter').addEventListener('change', filterPhones);
    document.getElementById('price-filter').addEventListener('change', filterPhones);
});