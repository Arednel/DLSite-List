(function () {
    'use strict';

    var activeElement = null;
    var previousDescribedBy = null;
    var tooltip = null;
    var tooltipId = 'title-tooltip-popover';
    var margin = 8;

    function tooltipText(element) {
        var title = element ? element.getAttribute('title') : '';

        return title ? title.trim() : '';
    }

    function findTitleElement(target) {
        if (!(target instanceof Element)) {
            return null;
        }

        var element = target.closest('[title]');

        return tooltipText(element) === '' ? null : element;
    }

    function ensureTooltip() {
        if (tooltip) {
            return tooltip;
        }

        tooltip = document.createElement('div');
        tooltip.id = tooltipId;
        tooltip.className = 'title-tooltip-popover';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.hidden = true;
        document.body.appendChild(tooltip);

        return tooltip;
    }

    function restoreDescribedBy() {
        if (!activeElement) {
            return;
        }

        if (previousDescribedBy === null) {
            activeElement.removeAttribute('aria-describedby');
        } else {
            activeElement.setAttribute('aria-describedby', previousDescribedBy);
        }

        previousDescribedBy = null;
    }

    function hideTooltip() {
        if (!tooltip || tooltip.hidden) {
            return;
        }

        restoreDescribedBy();
        tooltip.hidden = true;
        tooltip.textContent = '';
        activeElement = null;
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function positionTooltip(element) {
        var box = ensureTooltip();
        var rect = element.getBoundingClientRect();
        var tooltipRect = box.getBoundingClientRect();
        var scrollX = window.pageXOffset || document.documentElement.scrollLeft;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop;
        var viewportWidth = document.documentElement.clientWidth;
        var top = rect.top + scrollY - tooltipRect.height - margin;
        var placement = 'top';

        if (top < scrollY + margin) {
            top = rect.bottom + scrollY + margin;
            placement = 'bottom';
        }

        var left = rect.left + scrollX + (rect.width / 2) - (tooltipRect.width / 2);
        var minLeft = scrollX + margin;
        var maxLeft = scrollX + viewportWidth - tooltipRect.width - margin;

        left = clamp(left, minLeft, Math.max(minLeft, maxLeft));

        var arrowLeft = rect.left + scrollX + (rect.width / 2) - left;
        arrowLeft = clamp(arrowLeft, 12, tooltipRect.width - 12);

        box.dataset.placement = placement;
        box.style.left = left + 'px';
        box.style.top = top + 'px';
        box.style.setProperty('--title-tooltip-arrow-left', arrowLeft + 'px');
    }

    function showTooltip(element) {
        var text = tooltipText(element);

        if (text === '') {
            hideTooltip();
            return;
        }

        var box = ensureTooltip();

        if (activeElement !== element) {
            restoreDescribedBy();
            previousDescribedBy = element.getAttribute('aria-describedby');
            activeElement = element;
        }

        element.setAttribute('aria-describedby', tooltipId);
        box.textContent = text;
        box.hidden = false;
        positionTooltip(element);
    }

    document.addEventListener('click', function (event) {
        var element = findTitleElement(event.target);

        if (!element) {
            hideTooltip();
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        showTooltip(element);
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideTooltip();
            return;
        }

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        var element = findTitleElement(event.target);

        if (!element) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        showTooltip(element);
    }, true);

    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
})();
