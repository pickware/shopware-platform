import SpatialGallerySliderViewerPlugin from 'src/plugin/spatial/spatial-gallery-slider-viewer.plugin';
import SpatialBaseViewerPlugin from 'src/plugin/spatial/spatial-base-viewer.plugin';

jest.mock('src/plugin/spatial/utils/spatial-dive-load-util');

const mockDive = {
    start: jest.fn(),
    stop: jest.fn(),
};
window.DIVEQuickViewPlugin = {
    QuickView: jest.fn().mockResolvedValue(mockDive)
};

/**
 * @package innovation
 */
describe('SpatialGallerySliderViewerPlugin tests', () => {
    let spatialGallerySliderViewerPlugin;
    let mockElement;

    beforeEach(() => {
        mockElement = document.createElement('div');
        document.body.innerHTML = `
            <div class="zoom-modal-wrapper">
                <div class="zoom-modal">
                    <canvas id="canvasEl"></canvas>
                </div>
            </div>
        `;

        const modal = document.querySelector('.zoom-modal');
        modal.show = jest.fn(() => {
            modal.dispatchEvent(new Event('shown.bs.modal', { bubbles: true }));
        });

        modal.hide = jest.fn(() => {
            modal.dispatchEvent(new Event('hidden.bs.modal', { bubbles: true }));
        });

        spatialGallerySliderViewerPlugin = new SpatialGallerySliderViewerPlugin(mockElement, {
            sliderPosition: "1",
            lightIntensity: "100",
            modelUrl: "http://test/file.glb",
        });

        jest.clearAllMocks();

    });

    test('plugin initializes', () => {
        expect(typeof spatialGallerySliderViewerPlugin).toBe('object');
        expect(spatialGallerySliderViewerPlugin.sliderIndex).toBe(1);
    });

    test('init with undefined element will do nothing', () => {
        spatialGallerySliderViewerPlugin.el = undefined;
        spatialGallerySliderViewerPlugin.sliderIndex = undefined;

        spatialGallerySliderViewerPlugin.init();

        expect(spatialGallerySliderViewerPlugin.sliderIndex).toBe(undefined);
    });

    test('initViewer with defined model will not load model again', async () => {
        spatialGallerySliderViewerPlugin.ready = false;
        spatialGallerySliderViewerPlugin.model = {};
        const initRenderSpy = jest.spyOn(spatialGallerySliderViewerPlugin.spatialProductSliderRenderUtil, 'initRender');

        await spatialGallerySliderViewerPlugin.initViewer();

        expect(spatialGallerySliderViewerPlugin.ready).toBe(true);

        expect(initRenderSpy).toHaveBeenCalledTimes(1);
    });

    test('should disable slider canvas if super.initViewer throws', async () => {
        // Spy on base class initViewer to throw an error
        jest.spyOn(SpatialBaseViewerPlugin.prototype, 'initViewer').mockRejectedValueOnce(new Error('test error'));
        // Setup nested parent elements to match el.parentElement.parentElement
        const parent = document.createElement('div');
        const grandParent = document.createElement('div');
        parent.appendChild(mockElement);
        grandParent.appendChild(parent);
        // Call initViewer
        await spatialGallerySliderViewerPlugin.initViewer();
        // Assert the disabled class is added to the grand parent element
        expect(grandParent.classList.contains('gallery-slider-canvas-disabled')).toBe(true);
    });

    test('should start and stop rendering when showing and hiding the modal', async () => {
        await spatialGallerySliderViewerPlugin.initViewer();

        spatialGallerySliderViewerPlugin.dive = mockDive;

        const modalWrapper = document.querySelector('.zoom-modal-wrapper');
        const modal = modalWrapper?.querySelector('.zoom-modal');
        modal.show();

        await process.nextTick(() => {});

        expect(mockDive.stop).toHaveBeenCalled();

        modal.hide();

        await process.nextTick(() => {});

        expect(mockDive.start).toHaveBeenCalled();
    });
});
