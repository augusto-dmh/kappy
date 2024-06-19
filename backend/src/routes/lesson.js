import { Router } from "express";
import lessonController from "../controllers/lesson";

const router = new Router();

router.post("/lessons", lessonController.store);
router.get("/lessons", lessonController.index);
router.get("/lessons/:id", lessonController.show);
router.put("/lessons/:id", lessonController.update);
router.delete("/lessons/:id", lessonController.destroy);

export default router;
