from flask import Flask, request, jsonify

app = Flask(__name__)

# ================= ROOT TEST =================
@app.route("/", methods=["GET"])
def home():
    return jsonify({
        "status": "running",
        "message": "SK ML API is active"
    })

# ================= PREDICT =================
@app.route("/predict", methods=["POST"])
def predict():
    data = request.get_json()

    if not data:
        return jsonify({
            "error": "No JSON received"
        }), 400

    # ================= INPUTS =================
    total_budget = float(data.get("total_budget", 0))
    used_budget = float(data.get("used_budget", 0))
    approved = int(data.get("approved_projects", 0))
    rejected = int(data.get("rejected_projects", 0))
    total_projects = int(data.get("total_projects", 0))

    # ================= VALIDATION =================
    if total_budget <= 0:
        return jsonify({
            "error": "Invalid budget data"
        }), 400

    # ================= COMPUTE METRICS =================
    utilization = (used_budget / total_budget) * 100 if total_budget else 0

    approval_rate = (approved / total_projects) * 100 if total_projects > 0 else 0

    rejection_rate = (rejected / total_projects) * 100 if total_projects > 0 else 0

    # ================= SIMPLE ML SCORE =================
    score = (
        utilization * 0.5 +
        approval_rate * 0.4 -
        rejection_rate * 0.2
    )

    # ================= CLASSIFICATION =================
    if score >= 70:
        category = "High Performance"
        probability = 0.85
        recommendation = "Excellent performance. Maintain current strategy and expand programs."
    elif score >= 40:
        category = "Moderate Performance"
        probability = 0.60
        recommendation = "Stable execution. Improve proposal quality and participation rate."
    else:
        category = "Low Performance"
        probability = 0.30
        recommendation = "Weak performance. Improve planning, execution, and budget usage."

    # ================= RESPONSE =================
    return jsonify({
        "category": category,
        "success_probability": round(probability, 2),
        "budget_efficiency_score": round(score, 2),
        "utilization": round(utilization, 2),
        "recommendation": recommendation
    })

# ================= RUN =================
if __name__ == "__main__":
    import os
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)