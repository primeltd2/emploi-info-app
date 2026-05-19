import { addComment } from "../services/interactionService.js";
import { commentCreateSchema } from "../validators/queryValidators.js";

export async function commentCreate(req, res, next) {
  try {
    const input = commentCreateSchema.parse(req.body);
    res.status(201).json(await addComment(input));
  } catch (err) {
    next(err);
  }
}
