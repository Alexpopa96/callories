export default async function (url,name) {
    try {
        let response;
        await axios.get(url, {responseType: 'arraybuffer'}).then(res => {
            var fileURL = window.URL.createObjectURL(new Blob([res.data]));
            var fileLink = document.createElement("a");
            fileLink.href = fileURL;
            fileLink.setAttribute('download',name);
            document.body.appendChild(fileLink);
            fileLink.click();

            response = res.status;

        });
        return response;
    }
    catch {
        return 'Octavian';
    }

}
