document.addEventListener("DOMContentLoaded", function () {
  const chatIcon = document.querySelector(".chat-icon");
  const chatBox = document.getElementById("chatBox");
  if (chatIcon && chatBox) {
    // Toggle chat box when icon is clicked
    chatIcon.addEventListener("click", function (event) {
      event.stopPropagation(); // Prevent bubbling to document
      const isVisible = chatBox.style.display === "flex";
      chatBox.style.display = isVisible ? "none" : "flex";
    });

    // Hide chat box when clicking anywhere else
    document.addEventListener("click", function (event) {
      const isClickInsideChat = chatBox.contains(event.target);
      const isClickOnIcon = chatIcon.contains(event.target);

      if (!isClickInsideChat && !isClickOnIcon) {
        chatBox.style.display = "none";
      }
    });
  }
});
