import { Router } from "express";

const router = new Router();

import user from "./user";
import lesson from "./lesson";
import exercise from "./exercise";
import exerciseChoice from "./exerciseChoice";

const routes = [user, lesson, exercise, exerciseChoice];

routes.forEach((route) => {
  router.use(route);
});

export default router;
