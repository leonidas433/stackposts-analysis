<div class="border border-gray-400 rounded bg-white">
    
    <div class="d-flex pf-13">
        
        <div class="d-flex align-items-center gap-8">
            <div class="size-40 size-child">
                <img src="{{ theme_public_asset( "img/default.png" ) }}" class="align-self-center rounded-circle border cpv-avatar" alt="">
            </div>
            <div class="d-flex align-items-center justify-content-start">
                <div class="flex-grow-1 me-2 text-truncate">
                    <a href="javascript:void(0);" class="text-gray-800 text-hover-primary fs-14 fw-bold cpv-name">{{ __("Your name") }}</a>
                    <span class="text-gray-400 d-block fs-12">{{ date("M j") }}</span>
                </div>
            </div>
        </div>

    </div>

    <div class="mb-0">
        <div class="cpv-text fs-14 px-3 mb-3 text-truncate-5 mb-3"></div>

        <div class="cpv-media">
            <div class="cpv-img w-100 cpv-gmb-img d-none"></div>
            <div class="cpv-gmb-img-view w-100">
                <img src="{{ theme_public_asset( "img/default.png" ) }}" class="w-100">
            </div>
        </div>

        <div class="cpv-link d-none m-3 border b-r-10">
            <div class="cpv-link-img img-wrap-16x9 w-100 ratio ratio-4x3 border-end btl-r-10 btr-r-10">
                <img src="{{ theme_public_asset( "img/default.png" ) }}" class="w-100">
            </div>
            <div class="d-flex flex-column justify-content-center w-100 fs-12 pf-13">
                <div class="cpv-default">
                    <div class="h-12 bg-gray-300 mb-2"></div>
                    <div class="h-12 bg-gray-300 mb-2"></div>
                    <div class="h-12 bg-gray-300 mb-1"></div>
                    <div class="h-12 bg-gray-300 mb-1 wp-50"></div>
                </div>
                <div class="cpv-link-title fw-6 text-truncate-1"></div>
                <div class="cpv-link-desc text-gray-700 text-truncate-2"></div>
                <div class="cpv-link-web fs-12 fw-3 text-truncate-1"></div>
            </div>
        </div>

    </div>

    <div class="px-3 py-2 d-flex justify-content-end text-gray-600 align-items-center fs-16">
        <div class="d-flex justify-content-end gap-16">
            <div class="d-flex flex-stack">
                <div class="symbol symbol-45px">
                    <i class="fa-solid fa-share-nodes"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function vk_renderMediaCarousel(elements) {
    if (elements.length === 0) return '';

    const id = 'gmb-carousel-' + Math.random().toString(36).substr(2, 8); // unique ID

    let indicators = '';
    let items = '';

    elements.forEach((el, idx) => {
        const isActive = idx === 0 ? 'active' : '';

        indicators += `
            <button type="button" data-bs-target="#${id}" data-bs-slide-to="${idx}" class="${isActive}" aria-current="${isActive ? 'true' : 'false'}" aria-label="Slide ${idx + 1}"></button>
        `;

        items += `
            <div class="carousel-item ${isActive}">
                <div class="img-wrap">${el.outerHTML}</div>
            </div>
        `;
    });

    return `
        <div id="${id}" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                ${items}
            </div>
            ${elements.length > 1 ? `
            <button class="carousel-control-prev" type="button" data-bs-target="#${id}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#${id}" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>` : ''}
        </div>
    `;
}

function vk_onMediaItemsChange() {
    const vk_elements = document.querySelectorAll('.cpv-gmb-img > img, .cpv-gmb-img > div');
    if (vk_elements.length > 0) {
        const vk_mediaList = Array.from(vk_elements).filter(el =>
            el.tagName.toLowerCase() === 'img' || el.tagName.toLowerCase() === 'div'
        );

        const vk_rendered = vk_renderMediaCarousel(vk_mediaList);
        const viewContainer = document.querySelector('.cpv-gmb-img-view');
        if (viewContainer) {
            viewContainer.innerHTML = vk_rendered;
        }
    }
}

// Setup MutationObserver
var vk_container = document.querySelector('.cpv-gmb-img');
if (vk_container) {
    var vk_observer = new MutationObserver(vk_onMediaItemsChange);
    vk_observer.observe(vk_container, {
        childList: true,
        subtree: false,
        attributes: true,
        attributeFilter: ['src'],
    });

    vk_onMediaItemsChange();
}
</script>
