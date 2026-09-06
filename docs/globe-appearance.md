# Globe appearance

The default WorldGlobe uses react-globe.gl, loaded when the section approaches the viewport.
The Earth texture is served locally from public/images/globe/earth-day.jpg.
Texture source: https://github.com/turban/webgl-earth/blob/master/images/2_no_clouds_4k.jpg
This is a cloud-free surface texture; it is not a live satellite image.

To restore COBE, change the WorldGlobe import in resources/js/Components/Home/GroupNetwork.jsx to @/Components/Home/CobeGlobe and run npm run build. The original component and cobe dependency are retained.

Verification: production build; Chromium at 1440px and 375px, globe ready, no horizontal overflow or console errors.
