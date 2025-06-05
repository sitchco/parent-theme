function logHello() {
    console.log('Hello from parent theme');
}

function onDocumentReady() {
    logHello();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onDocumentReady);
} else {
    onDocumentReady();
}
