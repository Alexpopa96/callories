/** Scales a picture down to `maxSide` px on its longest side as a JPEG; keeps the original file if that fails. */
export async function shrinkImage(file, maxSide, name = 'photo.jpg') {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        return blob ? new File([blob], name, {type: 'image/jpeg'}) : file;
    } catch {
        return file;
    }
}
