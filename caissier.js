let cart = [];

// Éléments du DOM
const cartBody = document.getElementById("cart-body");
const totalEl = document.getElementById("total");
const changeEl = document.getElementById("change");
const receivedEl = document.getElementById("received");
const clearBtn = document.getElementById("clear");
const payBtn = document.getElementById("pay");
const paymentMethod = document.getElementById("payment-method");
const cashBlock = document.getElementById("cash-block");
const changeBlock = document.getElementById("change-block");

// Ajout des produits
document.querySelectorAll(".product-btn").forEach(button => {
    button.addEventListener("click", () => {

        const id = button.dataset.id;
        const name = button.dataset.name;
        const price = parseFloat(button.dataset.price);

        const produit = cart.find(item => item.id === id);

        if (produit) {
            produit.qty++;
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                qty: 1
            });
        }

        renderCart();
    });
});

// Affichage du panier
function renderCart() {

    cartBody.innerHTML = "";

    let total = 0;

    cart.forEach((item, index) => {

        const lineTotal = item.price * item.qty;
        total += lineTotal;

        const row = document.createElement("tr");

        row.innerHTML = `
            <td>${item.name}</td>

            <td>
                <button type="button" onclick="changeQty(${index}, -1)">-</button>
                ${item.qty}
                <button type="button" onclick="changeQty(${index}, 1)">+</button>
            </td>

            <td>${item.price.toFixed(2)} €</td>

            <td>${lineTotal.toFixed(2)} €</td>

            <td>
                <button type="button" onclick="removeItem(${index})">X</button>
            </td>
        `;

        cartBody.appendChild(row);
    });

    totalEl.textContent = total.toFixed(2);

    updateChange();
}

// Modification quantité
function changeQty(index, value) {

    cart[index].qty += value;

    if (cart[index].qty <= 0) {
        cart.splice(index, 1);
    }

    renderCart();
}

// Suppression produit
function removeItem(index) {

    cart.splice(index, 1);

    renderCart();
}

// Calcul monnaie à rendre
function updateChange() {

    const total = parseFloat(totalEl.textContent) || 0;
    const received = parseFloat(receivedEl.value) || 0;

    const change = received - total;

    changeEl.textContent = change.toFixed(2);
}

receivedEl.addEventListener("input", updateChange);

// Vider le panier
clearBtn.addEventListener("click", () => {

    if (!confirm("Vider le panier ?")) {
        return;
    }

    cart = [];

    receivedEl.value = "";

    renderCart();
});

// Paiement
payBtn.addEventListener("click", () => {

    if (cart.length === 0) {
        alert("Le panier est vide.");
        return;
    }

    const total = parseFloat(totalEl.textContent);
    const mode = paymentMethod.value;

    if (mode === "espece") {

        const received = parseFloat(receivedEl.value) || 0;

        if (received < total) {
            alert("Montant reçu insuffisant.");
            return;
        }
    }

    console.log("Bouton payer cliqué");
    console.log(cart);

    fetch("caissier.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            action: "payer",
            panier: JSON.stringify(cart)
        })
    })
    .then(response => response.json())
    .then(data => {

        if (data.success) {

            alert("Paiement enregistré avec succès");

            cart = [];
            receivedEl.value = "";
            paymentMethod.dispatchEvent(new Event("change"));
            renderCart();

        } else {

            alert(data.message);
        }
    })
    .catch(error => {

        console.error(error);
        alert("Erreur lors du paiement");

    });

});

// Initialisation
renderCart();