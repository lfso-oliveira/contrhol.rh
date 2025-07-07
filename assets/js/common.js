function checkLogin() {
  if (!localStorage.getItem('isLoggedIn')) {
    window.location.href = 'index.html';
  }
}

function logout() {
  localStorage.removeItem('isLoggedIn');
  window.location.href = 'index.html';
}

checkLogin(); 