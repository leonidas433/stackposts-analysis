<div class="border border-gray-400 rounded bg-white">
    
    <div class="pf-13">
        
        <div class="d-flex gap-8">
            <div class="size-40 size-child">
                <img src="{{ theme_public_asset( "img/default.png" ) }}" class="align-self-center rounded-circle border cpv-avatar" alt="">
            </div>
            <div class="d-flex flex-column wp-100">
                <div class="d-flex flex-row align-items-center justify-content-start mb-3">
                    <div class="me-2 text-truncate">
                        <a href="javascript:void(0);" class="text-gray-800 text-hover-primary fs-14 fw-bold cpv-name">{{ __("Your name") }}</a>
                    </div>
                    <span class="text-gray-400 d-block fs-12">{{ date("M j") }}</span>
                </div>

                <div class="mb-0">
                    <div class="cpv-text fs-14 mb-3 text-truncate-5"></div>

                    <div class="cpv-media">
                        <div class="cpv-img w-100 cpv-threads-img d-none"></div>
                        <div class="cpv-threads-img-view wp-100 img-wrap b-r-10 border border-gray-400">
                            <img src="{{ theme_public_asset( "img/default.png" ) }}" class="w-100">
                        </div>
                    </div>

                    <div class="cpv-link d-none my-3 border b-r-10">
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

                <div class="pe-3 pt-2 d-flex justify-content-between text-gray-800 align-items-center fs-20">
                    <div class="d-flex justify-content-between gap-16">
                        <div class="d-flex flex-stack">
                            <div class="symbol symbol-45px me-2">
                                <i class="fa-light fa-heart"></i>
                            </div>
                        </div>
                        <div class="d-flex flex-stack">
                            <div class="symbol symbol-45px me-2">
                                <i class="fa-light fa-comment"></i>
                            </div>
                        </div>
                        <div class="d-flex flex-stack">
                            <div class="symbol symbol-45px me-2">
                                <i class="fa-light fa-retweet"></i>
                            </div>
                        </div>
                        <div class="d-flex flex-stack">
                            <div class="symbol symbol-45px me-2">
                                <i class="fa-light fa-paper-plane"></i>
                            </div>
                        </div>
                    </div>
                    <div></div>
                </div>
            </div>

        </div>

    </div>

    

</div>

<script>
function tumblr_renderMediaGrid(elements) {
    var tumblr_total = elements.length;
    var tumblr_visible = elements.slice(0, 4);
    var tumblr_moreCount = tumblr_total - 4;

    let tumblr_html = '';

    if (tumblr_total === 1) {
        tumblr_html += `
            <div class="cpv-grid" style="grid-template-columns: 1fr;">
                <div class="img-wrap">${elements[0].outerHTML}</div>
            </div>
        `;
    } else if (tumblr_total === 2) {
        tumblr_html += `
            <div class="cpv-grid" style="grid-template-columns: repeat(2, 1fr);">
                ${tumblr_visible.map(el => `<div class="img-wrap">${el.outerHTML}</div>`).join('')}
            </div>
        `;
    } else if (tumblr_total === 3) {
        tumblr_html += `
            <div class="cpv-grid" style="grid-template-columns: 2fr 1fr; grid-template-rows: repeat(2, 1fr);">
                <div class="img-wrap" style="grid-row: span 2;">${elements[0].outerHTML}</div>
                <div class="img-wrap">${elements[1].outerHTML}</div>
                <div class="img-wrap">${elements[2].outerHTML}</div>
            </div>
        `;
    } else {
        tumblr_html += `<div class="cpv-grid" style="grid-template-columns: repeat(2, 1fr);">`;
        tumblr_visible.forEach((el, idx) => {
            var tumblr_isLast = idx === 3 && tumblr_moreCount > 0;
            var tumblr_overlay = tumblr_isLast ? `<div class="overlay">+${tumblr_moreCount}</div>` : '';
            tumblr_html += `<div class="img-wrap">${el.outerHTML}${tumblr_overlay}</div>`;
        });
        tumblr_html += `</div>`;
    }

    return tumblr_html;
}

function tumblr_onMediaItemsChange() {
    var tumblr_elements = document.querySelectorAll('.cpv-tumblr-img > img, .cpv-tumblr-img > div');
    if (tumblr_elements.length > 0) {
        var tumblr_mediaList = Array.from(tumblr_elements).filter(el =>
            el.tagName.toLowerCase() === 'img' || el.tagName.toLowerCase() === 'div'
        );

        var tumblr_rendered = tumblr_renderMediaGrid(tumblr_mediaList);
        document.querySelector('.cpv-tumblr-img-view').innerHTML = tumblr_rendered;
    }
}

// Setup MutationObserver
var tumblr_container = document.querySelector('.cpv-tumblr-img');
if (tumblr_container) {
    var tumblr_observer = new MutationObserver(tumblr_onMediaItemsChange);
    tumblr_observer.observe(tumblr_container, {
        childList: true,
        subtree: false,
        attributes: true,
        attributeFilter: ['src'],
    });

    tumblr_onMediaItemsChange();
}
</script>
