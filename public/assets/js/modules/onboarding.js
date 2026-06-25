(() => {
    'use strict';

    const module = document.querySelector('[data-module="onboarding"]');
    if (!module) return;
    module.querySelectorAll('.progress-bar').forEach((bar) => bar.classList.add('progress-bar-striped'));
})();
