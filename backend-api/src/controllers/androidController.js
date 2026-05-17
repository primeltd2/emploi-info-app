import { saveAndroidToken } from "../services/catalogService.js";
import { androidTokenSchema } from "../validators/queryValidators.js";

export async function androidTokenCreate(req, res, next) {
  try {
    const input = androidTokenSchema.parse(req.body);
    res.status(201).json({ status: "success", data: await saveAndroidToken(input) });
  } catch (err) {
    next(err);
  }
}
