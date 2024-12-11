import { Controller } from '@hotwired/stimulus';
// import {QRCode} from 'qrcodejs';
// import {QRCode} from 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/+esm';
import QRCode from 'qrcode'

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        text: String,
        options: Array,
    }
    static targets = ['img'];

    connect() {
        super.connect();
        console.log(this.textValue);
        QRCode.toDataURL(this.textValue)
            .then(url => {
                if (this.hasImgTarget) {
                    this.imgTarget.src=url;
                } else {
                    // assert that this is an img element
                    this.element.src=url;
                }
            })
            .catch(err => {
                console.error(err)
            })

// With async/await
//         const generateQR = async text => {
//             try {
//                 console.log(await QRCode.toDataURL(text))
//             } catch (err) {
//                 console.error(err)
//             }
//         }
    }

    // ...
}
